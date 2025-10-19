<?php

namespace ADT\Datagrid\Component;

use Nette\Security\Resource;

class DeleteParams
{
	public Resource $acl;
	public $onDelete = null;
	public $condition = null;

	public function __construct(Resource $acl, ?callable $onDelete = null, ?callable $condition = null)
	{
		$this->acl = $acl;
		$this->onDelete = $onDelete;
		$this->condition = $condition;
	}

	public function getAcl(): Resource
	{
		return $this->acl;
	}

	public function getCondition(): ?callable
	{
		return $this->condition;
	}
}
