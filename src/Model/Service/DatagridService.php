<?php

namespace ADT\Datagrid\Model\Service;

use ADT\Datagrid\Component\BaseGrid;
use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Model\Entities\GridExport;
use ADT\Datagrid\Model\Export\Excel\ExportExcel;
use ADT\Datagrid\Model\Export\Excel\Model\ExcelDataModel;
use ADT\DoctrineComponents\EntityManager;
use ADT\Files\Entities\File;
use Contributte\Datagrid\CsvDataModel;
use Contributte\Datagrid\Export\ExportCsv;
use Contributte\Datagrid\Row;
use Exception;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\Control;
use Nette\Application\UI\InvalidLinkException;
use Nette\Localization\Translator;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use ReflectionException;
use XLSXWriter;

final readonly class DatagridService
{
	public function __construct(
		private array         $config,
		private Mailer        $mailer,
		private EntityManager $em,
		private Translator    $translator,
		private LinkGenerator $linkGenerator,
	) {}

	/**
	 * @throws Exception
	 */
	public function processExport(GridExport $gridExport): void
	{
		ini_set('memory_limit', '10G');

		$items = $this->em->getRepository($gridExport->getEntityClass())
			->createQueryBuilder('e')
			->where('e.id IN (:ids)')
			->setParameter('ids', $gridExport->getValue())
			->getQuery()
			->getResult();
		
		$this->saveFile($gridExport, $items);

		$this->sendEmail($gridExport->getEmail(), $gridExport);
	}

	/**
	 * @throws InvalidLinkException
	 */
	private function sendEmail(string $email, GridExport $gridExport): void
	{
		$message = new Message();
		$message->addTo($email);
		$message->setSubject('Your export is ready');
		$message->setBody(sprintf(
			"You can download the file here: %s",
			$this->linkGenerator->link( $this->config['downloadLink'], ['id' => $gridExport->getId()]),
		));
		$this->mailer->send($message);
	}

	protected function normalizeGridName(string $gridName): string
	{
		// Odstraň poslední CamelCase slovo
		return preg_replace('/[A-Z][a-z]*$/', '', explode('-', $gridName)[1]);
	}

	/**
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function saveFile(GridExport $gridExport, array $items): void
	{
		$datagrid = new DataGrid();
		
		$columns = [];
		foreach ($gridExport->getColumns() as $_key => $_value) {
			$columns[] = new $_value['class']($datagrid, $_key, $_value['column'], $_value['name']);
		}
		
		$rows = [];
		foreach ($items as $item) {
			$rows[] = new Row($datagrid, $item, $datagrid->getPrimaryKey());
		}

		if ($gridExport->getExportClass() === ExportExcel::class) {
			$data = new ExcelDataModel($rows, $columns, $this->translator)->getSimpleData();
			$writer = new XLSXWriter();
			$writer->writeSheet($data);
			$content = $writer->writeToString();
			$ext = 'xlsx';
		} elseif ($gridExport->getExportClass() === ExportCsv::class) {
			$data = new CsvDataModel($rows, $columns, $this->translator)->getSimpleData();
			$stream = fopen('php://memory','w');
			foreach ($data as $_row) {
				fputcsv($stream, $_row, escape: '"');
			}
			rewind($stream);
			$content = stream_get_contents($stream);
			$ext = 'csv';
		} else {
			throw new Exception('Unsupported export type');
		}

		/** @var File $file */
		$file = new ($this->em->findEntityClassByInterface(File::class));
		$gridExport->setFile(
			$file
				->setTemporaryContent(
					$content,
					$this->normalizeGridName($gridExport->getGrid()) . '_' . $gridExport->getCreatedAt()->format('Y-m-d_H-i') . '.' . $ext
				)
		);
		
		$this->em->flush();
	}

	public static function getGridName(Control $control): string
	{
		return $control->getPresenter()->getName() . '-' . $control->lookup(BaseGrid::class)->getName();
	}
}
