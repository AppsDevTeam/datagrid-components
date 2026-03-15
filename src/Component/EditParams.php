<?php

namespace ADT\Datagrid\Component;

use Nette\Security\Resource;

class EditParams
{
	public ?Resource $acl = null;
	public string $redirect;
	public $condition = null;

	public function __construct(string $redirect, ?Resource $acl = null, ?callable $condition = null)
	{
		$this->acl = $acl;
		$this->redirect = $redirect;
		$this->condition = $condition;
	}

	public function getAcl(): string
	{
		return $this->acl->value;
	}

	public function getCondition(): ?callable
	{
		return $this->condition;
	}
}
