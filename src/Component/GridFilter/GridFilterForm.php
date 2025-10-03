<?php

namespace ADT\Datagrid\Component\GridFilter;

use ADT\Datagrid\Component\BaseGrid;
use ADT\DoctrineForms\BaseFormInterface;

interface GridFilterForm extends BaseFormInterface
{
	public function setGrid(BaseGrid $grid): static;
}