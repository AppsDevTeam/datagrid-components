<?php

declare(strict_types=1);

use ADT\Datagrid\Component\DataGrid;
use Nette\Application\AbortException;
use Nette\Application\Request;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/**
 * Export přidaný přes addExportCallback() dostane vyfiltrované řádky do svého callbacku. handleExport() ho
 * dřív posílal přes exporter jako ostatní exporty a callback se nikdy nezavolal.
 */
test('callback export gets the rows', function () {
	$presenter = new class extends Presenter {};
	(new ReflectionProperty(Presenter::class, 'request'))->setValue($presenter, new Request('Test', 'GET'));

	$grid = new DataGrid();
	$grid->setTranslator(new class implements Translator {
		public function translate(string|\Stringable $message, mixed ...$parameters): string
		{
			return (string) $message;
		}
	});
	$presenter->addComponent($grid, 'grid');
	$grid->setDataSource([['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']]);
	$grid->addColumnText('name', 'name');

	$exported = null;
	$grid->addExportCallback('callback', function (iterable $rows) use (&$exported): void {
		$exported = $rows;
	});

	try {
		$grid->handleExport((int) array_key_last((new ReflectionProperty($grid, 'exports'))->getValue($grid)));
	} catch (AbortException) {
	}
	Assert::same([1, 2], array_column(is_array($exported) ? $exported : iterator_to_array($exported), 'id'));
});
