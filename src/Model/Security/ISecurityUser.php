<?php

namespace ADT\Datagrid\Model\Security;

interface ISecurityUser
{
	public function isAllowedIn(array $resources = []): array;
}