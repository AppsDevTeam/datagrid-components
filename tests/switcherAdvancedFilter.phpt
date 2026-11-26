<?php

declare(strict_types=1);

use ADT\Datagrid\Component\DataGrid;
use Nette\Application\Request;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

$translator = new class implements Translator {
	public function translate(string|\Stringable $message, mixed ...$parameters): string
	{
		return (string) $message;
	}
};

/**
 * Grid pripojeny k presenteru, protoze addSwitcher() cte POST z requestu.
 */
$createGrid = function () use ($translator): DataGrid {
	$presenter = new class extends Presenter {};
	(new ReflectionProperty(Presenter::class, 'request'))->setValue($presenter, new Request('Test', 'GET'));

	$grid = new DataGrid();
	$grid->setTranslator($translator);
	$presenter->addComponent($grid, 'grid');
	$grid->addFilterText('number', 'number');

	return $grid;
};

$getFieldIds = fn (DataGrid $grid): array => array_column($grid->getGridFilterFields(), 'id');


// zpetna kompatibilita: bez nastaveni dialog prepinac nabizi jako dosud
test('switcher is in advanced filter by default', function () use ($createGrid, $getFieldIds) {
	$grid = $createGrid();
	$grid->addSwitcher('onlyManual', 'onlyManual', fn () => null);

	Assert::same(['number', 'onlyManual'], $getFieldIds($grid));
	Assert::true($grid->getFilter('onlyManual')->isInAdvancedFilter());
});

test('switcher can be left out of advanced filter', function () use ($createGrid, $getFieldIds) {
	$grid = $createGrid();
	$grid->addSwitcher('onlyManual', 'onlyManual', fn () => null, inAdvancedFilter: false);

	Assert::same(['number'], $getFieldIds($grid));
});

test('switcher added directly can be left out of advanced filter', function () use ($createGrid, $getFieldIds) {
	$grid = $createGrid();
	$grid->addFilterSwitcher('onlyManual', 'onlyManual')->setInAdvancedFilter(false);

	Assert::same(['number'], $getFieldIds($grid));
});
