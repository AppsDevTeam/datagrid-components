<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;

class GridFilterTrait
{
	#[ORM\Column(nullable: false)]
	protected string $grid;

	#[ORM\Column(nullable: false)]
	protected string $name;

	#[Column(type: "json", nullable: false)]
	protected array $value = [];

	public function getGrid(): string
	{
		return $this->grid;
	}

	public function setGrid(string $grid): self
	{
		$this->grid = $grid;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	public function getValue(): array
	{
		return $this->value;
	}

	public function setValue(array $value): self
	{
		$this->value = $value;
		return $this;
	}
}
