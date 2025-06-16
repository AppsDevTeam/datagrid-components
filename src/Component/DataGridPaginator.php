<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use Nette;

/**
 * @property-read Nette\Application\UI\ITemplate $template
 */
class DataGridPaginator extends \Contributte\Datagrid\Components\DatagridPaginator\DatagridPaginator
{
	public function getTemplateFile(): string
	{
		return __DIR__ . '/DataGridPaginator.latte';
	}
}
