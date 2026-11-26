<?php

declare(strict_types=1);

namespace ADT\Datagrid\Filter;

/** Výběrový filtr, kterému jde volby změnit i po přidání (třeba volba „Vše“ u výchozího filtru). */
class FilterSelect extends \Contributte\Datagrid\Filter\FilterSelect
{
	/** @param array<int|string, mixed> $options */
	public function setOptions(array $options): static
	{
		$this->options = $options;
		return $this;
	}
}
