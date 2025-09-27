<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Entities;

use ADT\DoctrineComponents\Entities\Entity;
use ADT\Files\Entities\File;
use DateTimeImmutable;

interface GridExport extends Entity
{
	public function getGrid(): string;
	public function setGrid(string $grid): static;
	public function getEntityClass(): string;
	public function setEntityClass(string $entityClass): static;
	public function getExportClass(): string;
	public function setExportClass(string $exportClass): static;
	public function getFile(): ?File;
	public function setFile(?File $file): static;
	public function getValue(): array;
	public function setValue(array $value): static;
	public function getEmail(): ?string;
	public function setEmail(?string $email): static;
	public function getColumns(): array;
	public function setColumns(array $columns): static;
	public function getCreatedAt(): DateTimeImmutable;
	public function getInBackground(): bool;
	public function setInBackground(bool $inBackground): static;
}
