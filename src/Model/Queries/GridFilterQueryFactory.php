<?php

namespace ADT\Datagrid\Model\Queries;

interface GridFilterQueryFactory
{
	public function create(): GridFilterQuery;
}
