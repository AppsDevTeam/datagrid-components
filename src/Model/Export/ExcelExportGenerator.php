<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Export;

use ADT\Datagrid\Model\Export\Excel\ExcelDataModel;
use ADT\Exporter\Model\Service\ExportFileGenerator;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Row;
use Contributte\Datagrid\Column\ColumnDateTime;
use Nette\Localization\Translator;
use XLSXWriter;

/**
 * Excel generator pro adt/exporter - extrahovano z drivejsi
 * DatagridService::saveFile(). Sloupce prijima v serializovanem tvaru
 * z DataGrid::handleExport (['klic' => ['name','column','class'], ...]).
 */
final readonly class ExcelExportGenerator implements ExportFileGenerator
{
	public function __construct(private Translator $translator) {}

	public function generate(array $items, array $columns, string $identifier): string
	{
		[$rows, $cols] = GeneratorHelper::buildRowsAndColumns($items, $columns);

		$data = new ExcelDataModel($rows, $cols, $this->translator)->getSimpleData();
		$writer = new XLSXWriter();
		$writer->writeSheet($data);

		$path = tempnam(sys_get_temp_dir(), 'export') . '_' . GeneratorHelper::fileName($identifier) . '.xlsx';
		file_put_contents($path, $writer->writeToString());
		return $path;
	}
}
