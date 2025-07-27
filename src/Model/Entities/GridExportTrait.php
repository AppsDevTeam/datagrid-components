<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Entities;

use ADT\Files\Entities\File;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;

trait GridExportTrait
{
	#[ORM\Column(nullable: false)]
	protected \DateTimeImmutable $createdAt;

	#[ORM\Column(nullable: false)]
	protected string $grid;

	#[ORM\Column(nullable: false)]
	protected string $entityClass;

	#[ORM\Column(nullable: false)]
	protected string $exportClass;

	#[ORM\Column(nullable: false)]
	protected array $columns;

	#[ORM\Column(nullable: false)]
	protected array $value;

	#[ORM\Column(nullable: false, options: ['default' => 0])]
	protected bool $inBackground = false;

	#[ORM\Column(nullable: true)]
	protected ?string $email = null;

	#[ORM\OneToOne(targetEntity: 'File', cascade: ['persist', 'remove'], orphanRemoval: true)]
	protected ?File $file = null;

	public function __construct()
	{
		$this->createdAt = new \DateTimeImmutable();
	}

	public function getGrid(): string
	{
		return $this->grid;
	}

	public function setGrid(string $grid): static
	{
		$this->grid = $grid;
		return $this;
	}

	public function getEntityClass(): string
	{
		return $this->entityClass;
	}

	public function setEntityClass(string $entityClass): static
	{
		$this->entityClass = $entityClass;
		return $this;
	}

	public function getExportClass(): string
	{
		return $this->exportClass;
	}

	public function setExportClass(string $exportClass): static
	{
		$this->exportClass = $exportClass;
		return $this;
	}

	public function getFile(): ?File
	{
		return $this->file;
	}

	public function setFile(?File $file): static
	{
		$this->file = $file;
		return $this;
	}

	public function getValue(): array
	{
		return $this->value;
	}

	public function setValue(array $value): static
	{
		$this->value = $value;
		return $this;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(?string $email): static
	{
		$this->email = $email;
		return $this;
	}

	public function getColumns(): array
	{
		return $this->columns;
	}

	public function setColumns(array $columns): static
	{
		$this->columns = $columns;
		return $this;
	}

	public function getCreatedAt(): \DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function getInBackground(): bool
	{
		return $this->inBackground;
	}

	public function setInBackground(bool $inBackground): static
	{
		$this->inBackground = $inBackground;
		return $this;
	}
}
