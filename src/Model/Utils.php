<?php

namespace ADT\Datagrid\Model;

class Utils
{

	final static public function realEmpty($value): bool
	{
		return empty($value) && $value !== 0 && $value !== '0';
	}
}