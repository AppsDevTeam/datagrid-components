<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Export\Csv;

use ADT\Datagrid\Model\Export\FormulaGuard;
use Contributte\Datagrid\CsvDataModel;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Export\ExportCsv as ContributteExportCsv;
use Contributte\Datagrid\Response\CsvResponse;

/**
 * CSV export gridu s ochranou proti formula injection - viz FormulaGuard.
 *
 * Contributte si callback sestavuje v konstruktoru a drzi ho v private metode, takze se
 * nahrazuje az zpetne pres protected $callback. Jinak je telo shodne s puvodnim.
 */
class ExportCsv extends ContributteExportCsv
{
	public function __construct(
		Datagrid $grid,
		string $text,
		string $name,
		bool $filtered,
		string $outputEncoding = 'utf-8',
		string $delimiter = ';',
		bool $includeBom = false,
	)
	{
		parent::__construct($grid, $text, $name, $filtered, $outputEncoding, $delimiter, $includeBom);

		if (!str_contains($name, '.csv')) {
			$name .= '.csv';
		}

		$this->callback = function (array $data, Datagrid $grid) use ($name, $outputEncoding, $delimiter, $includeBom): void {
			$columns = $this->getColumns();

			if ($columns === []) {
				$columns = $this->grid->getColumns();
			}

			$csvDataModel = new CsvDataModel($data, $columns, $this->grid->getTranslator());

			$this->grid->getPresenter()->sendResponse(new CsvResponse(
				FormulaGuard::escapeRows($csvDataModel->getSimpleData()),
				$name,
				$outputEncoding,
				$delimiter,
				$includeBom,
			));
		};
	}
}
