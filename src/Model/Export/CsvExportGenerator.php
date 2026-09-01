<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Export;

use Contributte\Datagrid\CsvDataModel;
use ADT\Exporter\Model\Service\ExportFileGenerator;
use Nette\Localization\Translator;

/** CSV generator pro adt/exporter - viz ExcelExportGenerator. */
final readonly class CsvExportGenerator implements ExportFileGenerator
{
	public function __construct(private Translator $translator) {}

	public function generate(array $sections, string $identifier): string
	{
		if (count($sections) !== 1) {
			throw new \InvalidArgumentException('CSV export podporuje prave jednu sekci - pro vice sekci pouzij Excel.');
		}
		$section = reset($sections);

		if (GeneratorHelper::isRawRows($section['items'])) {
			$data = array_merge([array_values($section['columns'])], array_values($section['items']));
		} else {
			[$rows, $cols] = GeneratorHelper::buildRowsAndColumns($section['items'], $section['columns']);
			$data = new CsvDataModel($rows, $cols, $this->translator)->getSimpleData();
		}
		$stream = fopen('php://memory', 'w');
		foreach ($data as $row) {
			fputcsv($stream, $row, escape: '"');
		}
		rewind($stream);

		// unikatni TMP adresar + ciste jmeno souboru - basename se pouziva
		// jako nazev pri downloadu i pro ulozeni (Exporter prida jen id-)
		$dir = sys_get_temp_dir() . '/' . uniqid('export_', true);
		mkdir($dir);
		$path = $dir . '/' . GeneratorHelper::fileName($identifier) . '.csv';
		file_put_contents($path, stream_get_contents($stream));
		return $path;
	}
}
