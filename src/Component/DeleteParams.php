<?php

namespace ADT\Datagrid\Component;

class DeleteParams
{
	public string $acl;
	public $onDelete = null;
	public $condition = null;

	public function __construct(string $acl, ?callable $onDelete = null, ?callable $condition = null)
	{
		$this->acl = $acl;
		$this->onDelete = $onDelete;
		$this->condition = $condition;
	}

	public function getAcl(): string
	{
		return $this->acl;
	}

	public function getCondition(): ?callable
	{
		return $this->condition;
	}
}
