<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Queries;

use ADT\DoctrineComponents\QueryObject\QueryObjectInterface;

interface GridFilterQuery extends QueryObjectInterface
{
	public function byGrid(string $grid): static;
	public function byName(string $name): static;
	public function byIdNot(int|array $id): static;
	public function count(): int;
}
