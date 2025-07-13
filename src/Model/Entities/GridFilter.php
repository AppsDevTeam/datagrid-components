<?php
declare(strict_types=1);

namespace ADT\Datagrid\Model\Entities;

interface GridFilter
{
	public function getId(): string;
	public function getGrid(): string;
	public function setGrid(string $grid): static;
	public function getName(): string;
	public function setName(string $name): static;
	public function getValue(): array;
	public function setValue(array $value): static;
}