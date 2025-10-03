<?php

namespace ADT\Datagrid\Component\GridFilter;

interface GridFilterFormFactory
{
	public function create(): GridFilterForm;
}