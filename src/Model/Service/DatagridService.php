<?php

namespace ADT\Datagrid\Model\Service;

use ADT\Datagrid\Component\BaseGrid;
use ADT\Datagrid\Model\Entities\GridExport;
use ADT\Datagrid\Model\Entities\GridFilter;
use ADT\Datagrid\Model\Export\Excel\ExportExcel;
use ADT\Datagrid\Model\Export\Excel\Model\ExcelDataModel;
use ADT\DoctrineComponents\EntityManager;
use ADT\QueryObjectDataSource\QueryObjectDataSource;
use Contributte\Datagrid\Column\ColumnText;
use Contributte\Datagrid\CsvDataModel;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Export\Export;
use Contributte\Datagrid\Export\ExportCsv;
use Contributte\Datagrid\Response\CsvResponse;
use Contributte\Datagrid\Row;
use DateTimeImmutable;
use DateTimeInterface;
use DoctrineBatchUtils\BatchProcessing\SimpleBatchIteratorAggregate;
use Exception;
use Google\Api\Control;
use Nette\Application\LinkGenerator;
use Nette\Localization\Translator;
use Nette\Mail\Message;
use Ublaboo\DataGrid\Utils\PropertyAccessHelper;

final class DatagridService
{
	public function __construct(
		private readonly array $config,
		private readonly \Nette\Mail\Mailer $mailer,
		private readonly EntityManager $em,
		private readonly Translator $translator,
		private readonly LinkGenerator $linkGenerator,
	) {}

	/**
	 * @throws Exception
	 */
	public function processExport(GridExport $gridExport): void
	{
		$header = [];
		$firstRow = true;
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

	protected function getClassBaseName($gridName)
	{
		// Odstraň poslední CamelCase slovo
		return preg_replace('/[A-Z][a-z]*$/', '', explode('-', $gridName)[0]);
	}
	
	public function saveFile(GridExport $gridExport, array $items)
	{
		$datagrid = new \ADT\Datagrid\Component\DataGrid();
		
		$columns = [];
		foreach ($gridExport->getColumns() as $_key => $_value) {
			$columns[] = new ColumnText($datagrid, $_key, $_value['column'], $_value['name']);
		}
		
		$rows = [];
		foreach ($items as $item) {
			$rows[] = new Row($datagrid, $item, $datagrid->getPrimaryKey());
		}

		if ($gridExport->getExportClass() === ExportExcel::class) {
			$data = new ExcelDataModel($rows, $columns, $this->translator)->getSimpleData();
			$writer = new \XLSXWriter();
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
		}

		/** @var \ADT\Files\Entities\File $file */
		$file = new ($this->em->findEntityByInterface(\ADT\Files\Entities\File::class));
		$gridExport->setFile(
			$file
				->setTemporaryContent(
					$content,
					$this->getClassBaseName($gridExport->getGrid()) . '_' . $gridExport->getCreatedAt()->format('Y-m-d_H-i') . '.' . $ext
				)
		);
		
		$this->em->flush();
	}

	public static function getGridName(\Nette\Application\UI\Control $control): string
	{
		return $control->getPresenter()->getName() . '-' . $control->lookup(BaseGrid::class)->getName();
	}
}
