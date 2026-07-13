<?php declare(strict_types = 1);

namespace ADT\Datagrid\Column;

use ADT\Datagrid\Component\DataGrid;
use Contributte\Datagrid\Column\ColumnText as BaseColumnText;
use Contributte\Datagrid\Filter\Filter;
use Contributte\Datagrid\Filter\FilterSelect;

class ColumnText extends BaseColumnText
{
	public function setFilterBoolean(?string $column = null, ?bool $nullable = null): FilterSelect|Filter
	{
		assert($this->grid instanceof DataGrid);

		return $this->grid->addFilterBoolean($this->key, $this->name, $column ?? $this->column, $nullable);
	}
}
