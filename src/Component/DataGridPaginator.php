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
	private const int SURROUNDING_PAGES = 2;

	public function getTemplateFile(): string
	{
		return __DIR__ . '/DataGridPaginator.latte';
	}

	public function render(): void
	{
		$this->getTemplate()->steps = $this->computeSlidingWindowSteps($this->getPaginator());
		parent::render();
	}

	/**
	 * @return int[]
	 */
	private function computeSlidingWindowSteps(Paginator $paginator): array
	{
		if ($paginator->pageCount < 2) {
			return [$paginator->page];
		}

		$steps = range(
			max($paginator->firstPage, $paginator->page - self::SURROUNDING_PAGES),
			(int) min($paginator->lastPage, $paginator->page + self::SURROUNDING_PAGES)
		);
		$steps[] = $paginator->firstPage;
		$steps[] = $paginator->lastPage;

		$steps = array_values(array_unique($steps));
		sort($steps);

		return $steps;
	}
}
