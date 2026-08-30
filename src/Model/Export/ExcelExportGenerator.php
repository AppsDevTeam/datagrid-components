<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Export;

use ADT\Datagrid\Model\Export\Excel\Model\ExcelDataModel;
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

	public function generate(array $sections, string $identifier): string
	{
		$writer = new XLSXWriter();
		foreach ($sections as $name => $section) {
			if (GeneratorHelper::isRawRows($section['items'])) {
				// agregatova sekce: radky jsou hotove (snapshot z auditu)
				$data = array_merge([array_values($section['columns'])], array_values($section['items']));
			} else {
				[$rows, $cols] = GeneratorHelper::buildRowsAndColumns($section['items'], $section['columns']);
				$data = new ExcelDataModel($rows, $cols, $this->translator)->getSimpleData();
			}
			$writer->writeSheet($data, mb_substr($name, 0, 31)); // Excel limit nazvu sheetu
		}

		// unikatni TMP adresar + ciste jmeno souboru - basename se pouziva
		// jako nazev pri downloadu i pro ulozeni (Exporter prida jen id-)
		$dir = sys_get_temp_dir() . '/' . uniqid('export_', true);
		mkdir($dir);
		$path = $dir . '/' . GeneratorHelper::fileName($identifier) . '.xlsx';
		file_put_contents($path, $writer->writeToString());
		return $path;
	}
}
