<?php

namespace ADT\Datagrid\Component;

use Nette\Security\Resource;

class EditParams
{
	public Resource $acl;
	public string $redirect;
	public $condition;

	public function __construct(Resource $acl, string $redirect, ?callable $condition = null)
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
