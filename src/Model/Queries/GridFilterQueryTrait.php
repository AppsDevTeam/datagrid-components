<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Queries;

use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;

trait GridFilterQueryTrait
{
	abstract public function by(array|string $column, mixed $value = null, QueryObjectByMode $mode = QueryObjectByMode::AUTO): static;
	abstract public function orderBy(array|string $field, ?string $order = null): static;
	abstract public function byIdNot(int|array $id): static;
	abstract public function count(): int;

	public function byGrid(string $grid): static
	{
		return $this->by('grid', $grid);
	}

	public function byName(string $name): static
	{
		return $this->by('name', $name);
	}

	protected function setDefaultOrder(): void
	{
		$this->orderBy('name', 'ASC');
	}

	protected function getPrimaryEntityAlias(): ?string
	{
		return 'e';
	}
}
