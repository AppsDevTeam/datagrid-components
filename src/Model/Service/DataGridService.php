<?php

namespace ADT\Datagrid\Model\Service;

use App\Model\Database\EntityManager;
use App\Model\Entity\File;
use App\Model\Entity\Item;
use App\Model\Mailer\Mailer;
use DateTimeImmutable;
use DateTimeInterface;
use DoctrineBatchUtils\BatchProcessing\SimpleBatchIteratorAggregate;
use Exception;
use Nette\Mail\Message;
use Ublaboo\DataGrid\Utils\PropertyAccessHelper;

class DataGridService
{
	const FILE_ID_VARIABLE = '__FILE__';

	public function __construct(
		private readonly Mailer $mailer,
		private readonly EntityManager $entityManager,
	) {}

	/**
	 * @param array $ids
	 * @param array $columns {
	 *     name: string,
	 *     column: string,
	 * }
	 * @param string $entityClass
	 * @param string $userMail
	 * @param string $downloadLink
	 * @return void
	 * @throws Exception
	 */
	public function export(array $ids, array $columns, string $entityClass, string $userMail, string $downloadLink): void
	{
		$header = [];
		$firstRow = true;
		ini_set('memory_limit', '10G');
		$file = fopen('php://memory','w');

		$items = SimpleBatchIteratorAggregate::fromQuery(
			$this->entityManager->getRepository($entityClass)
				->createQueryBuilder('e')
				->where('e.id IN (:ids)')
				->setParameter('ids', $ids)
				->getQuery(),
			1000
		);

		/** @var Item $item */
		foreach ($items as $item) {
			$row = [];

			foreach ($columns as $column) {
				if ($firstRow === true) {
					$header[] = $column['name'];
				}

				$row[] = $this->parseColumn($item, $column['column']);
			}

			if ($firstRow === true) {
				$firstRow = false;
				fputcsv($file, $header, ';');
			}

			fputcsv($file, $row, ';');
		}

		rewind($file);
		$fileName = mb_strtolower(substr($entityClass, strrpos($entityClass, '\\') + 1));
		$exportedFile = (new File())
			->setExpiresAt((new DateTimeImmutable())->modify('+72 hours'))
			->setTemporaryContent(stream_get_contents($file), $fileName . '.csv');
		fclose($file);

		$this->entityManager->persist($exportedFile);
		$this->entityManager->flush();

		$this->sendEmail($userMail, $downloadLink, $exportedFile);
	}

	private function sendEmail(string $email, string $downloadLink,  File $file): void
	{
		$message = new Message();
		$message->addTo($email);
		$message->setSubject(sprintf('Exported file %s is ready', $file->getOriginalName()));
		$message->setBody(sprintf(
			"The file %s is ready for download.\nYou can download the file from %s\n\nThe file will be available until %s.",
			$file->getOriginalName(),
			str_replace(self::FILE_ID_VARIABLE, $file->getId(), $downloadLink),
			$file->getExpiresAt()->format('Y-m-d H:i:s')
		));
		$this->mailer->send($message);
	}

	private function parseColumn($item, $key): ?string
	{
		$properties = explode('.', $key);
		$value = $item;
		$accessor = PropertyAccessHelper::getAccessor();

		while ($property = array_shift($properties)) {
			if (!is_object($value) && ! (bool) $value) {
				return null;
			}

			$value = $accessor->getValue($value, $property);
		}

		if (is_object($value) && method_exists($value, '__toString')) {
			return (string) $value;
		}
		if (is_array($value)) {
			return implode(',', $value);
		}
		if ($value instanceof DateTimeInterface) {
			return $value->format('j. n. Y H:i:s');
		}

		return $value;
	}
}
