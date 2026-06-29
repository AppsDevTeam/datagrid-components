<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use Nette;
use Nette\Utils\Paginator;

/**
 * @property-read Nette\Application\UI\ITemplate $template
 */
class DataGridPaginator extends \Contributte\Datagrid\Components\DatagridPaginator\DatagridPaginator
{
	private const int WINDOW_SIZE = 6;

	public function getTemplateFile(): string
	{
		return __DIR__ . '/DataGridPaginator.latte';
	}

	public function render(): void
	{
		$this->getTemplate()->steps = $this->computeWindowSteps($this->getPaginator());
		parent::render();
	}

	/**
	 * @return int[]
	 */
	private function computeWindowSteps(Paginator $paginator): array
	{
		if ($paginator->pageCount < 2) {
			return [$paginator->page];
		}

		$first = $paginator->firstPage;
		$last = $paginator->lastPage;

		$blockIndex = intdiv($paginator->page - $first, self::WINDOW_SIZE);
		$start = $first + $blockIndex * self::WINDOW_SIZE;
		$end = min($last, $start + self::WINDOW_SIZE - 1);

		$steps = range($start, $end);
		$steps[] = $first;
		$steps[] = $last;

		$steps = array_values(array_unique($steps));
		sort($steps);

		return $steps;
	}
}
