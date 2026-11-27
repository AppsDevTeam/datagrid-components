<?php

declare(strict_types=1);

namespace ADT\Datagrid\Filter;

/** Textový filtr se sloupci zvenku - hledání ve více filtrech najednou je potřebuje bez vyplněné hodnoty filtru. */
class FilterText extends \Contributte\Datagrid\Filter\FilterText
{
	/** @return list<string> */
	public function getColumns(): array
	{
		return array_values($this->columns);
	}
}
