<?php

namespace ADT\Datagrid\Component\GridFilter;

use ADT\Datagrid\Component\BaseGrid;

interface GridFilterForm
{
	public function setGrid(BaseGrid $grid): static;
}