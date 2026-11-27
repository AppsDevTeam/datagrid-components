<?php

declare(strict_types=1);

use ADT\Datagrid\Component\DataGrid;
use Contributte\Datagrid\Exception\DataGridException;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/** Export na pozadí potřebuje query object; grid nad polem ho nemá a řekne to srozumitelně. */
test('grid without query object data source refuses', function () {
	$grid = new DataGrid();
	Assert::exception(fn() => $grid->getFilteredQueryObject(), DataGridException::class);

	$grid->setDataSource([['id' => 1]]);
	Assert::exception(fn() => $grid->getFilteredQueryObject(), DataGridException::class, 'Grid nemá zdroj dat nad query objektem.');
});
