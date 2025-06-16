<?php declare(strict_types = 1);

namespace ADT\Datagrid\Model\Export\Excel;

use ADT\Datagrid\Model\Export\Excel\Model\ExcelDataModel;
use ADT\Datagrid\Model\Export\Excel\Response\ExcelResponse;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Export\Export;

class ExportExcel extends Export
{
	public function __construct(
		Datagrid $grid,
		string $text,
		string $name,
		bool $filtered,
	)
	{
		if (!str_contains($name, '.xlsx')) {
			$name .= '.xlsx';
		}

		parent::__construct(
			$grid,
			$text,
			$this->getExportCallback($name),
			$filtered
		);
	}

	private function getExportCallback(string $name): callable
	{
		return function (
			array $data,
			Datagrid $grid
		) use ($name): void {
			$columns = $this->getColumns();

			if ($columns === []) {
				$columns = $this->grid->getColumns();
			}

			$excelDataModel = new ExcelDataModel($data, $columns, $this->grid->getTranslator());

			$this->grid->getPresenter()->sendResponse(new ExcelResponse($excelDataModel->getSimpleData(), $name));
		};
	}
}
