<?php

namespace ADT\Datagrid\Component;

class EditParams
{
	public string $acl;
	public string $redirect;
	public $condition;

	public function __construct(string $acl, string $redirect, ?callable $condition = null)
	{
		$this->acl = $acl;
		$this->redirect = $redirect;
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
