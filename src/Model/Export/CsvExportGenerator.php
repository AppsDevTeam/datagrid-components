<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Export;

use ADT\Datagrid\Model\Export\Csv\CsvDataModel;
use ADT\Exporter\Model\Service\ExportFileGenerator;
use Nette\Localization\Translator;

/** CSV generator pro adt/exporter - viz ExcelExportGenerator. */
final readonly class CsvExportGenerator implements ExportFileGenerator
{
	public function __construct(private Translator $translator) {}

	public function generate(array $items, array $columns, string $identifier): string
	{
		[$rows, $cols] = GeneratorHelper::buildRowsAndColumns($items, $columns);

		$data = new CsvDataModel($rows, $cols, $this->translator)->getSimpleData();
		$stream = fopen('php://memory', 'w');
		foreach ($data as $row) {
			fputcsv($stream, $row, escape: '"');
		}
		rewind($stream);

		$path = tempnam(sys_get_temp_dir(), 'export') . '_' . GeneratorHelper::fileName($identifier) . '.csv';
		file_put_contents($path, stream_get_contents($stream));
		return $path;
	}
}
