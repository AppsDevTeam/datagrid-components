<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Queries;

interface GridFilterQuery
{
	public function byGrid(string $grid): static;
	public function byName(string $name): static;
	public function byIdNot(int|array $id): static;
	public function count(): int;
}
