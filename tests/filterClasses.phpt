<?php

declare(strict_types=1);

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Filter\FilterSelect;
use ADT\Datagrid\Filter\FilterText;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

test('text filter exposes its columns', function () {
	$filter = (new DataGrid())->addFilterText('name', 'name', ['firstName', 'lastName']);

	Assert::type(FilterText::class, $filter);
	Assert::same(['firstName', 'lastName'], $filter->getColumns());
	Assert::same(['name'], (new DataGrid())->addFilterText('name', 'name')->getColumns());
});

test('select filter options can be replaced', function () {
	$filter = (new DataGrid())->addFilterSelect('state', 'state', ['' => 'all', 1 => 'yes']);

	Assert::type(FilterSelect::class, $filter);
	Assert::same([1 => 'yes'], $filter->setOptions([1 => 'yes'])->getOptions());
});
