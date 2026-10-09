<?php

declare(strict_types=1);

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Component\DataGridPaginator;
use Nette\Localization\Translator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/**
 * Grid musi kreslit vlastni strankovani (DataGridPaginator.latte), ne vychozi z contributte.
 * Import contributte DatagridPaginator v DataGrid.php se lisi jen velikosti pismen a PHP nazvy
 * trid nerozlisuje, takze bez aliasu `new DataGridPaginator` vyrobil contributte paginator.
 */

$translator = new class implements Translator {
	public function translate(string|\Stringable $message, mixed ...$parameters): string
	{
		return (string) $message;
	}
};


test('grid vytvari vlastni paginator', function () use ($translator) {
	$grid = new DataGrid();
	$grid->setTranslator($translator);

	$paginator = $grid->createComponentPaginator();

	Assert::type(DataGridPaginator::class, $paginator);
	Assert::same(DataGridPaginator::class, $paginator::class);
});


test('vlastni paginator kresli vlastni sablonu', function () use ($translator) {
	$grid = new DataGrid();
	$grid->setTranslator($translator);

	Assert::same(
		realpath(__DIR__ . '/../src/Component/DataGridPaginator.latte'),
		realpath($grid->createComponentPaginator()->getTemplateFile()),
	);
});


test('paginator prebira stranku a pocet polozek na stranku z gridu', function () use ($translator) {
	$grid = new DataGrid();
	$grid->setTranslator($translator);
	$grid->page = 3;
	$grid->setItemsPerPageList([20, 50], false);

	$paginator = $grid->createComponentPaginator()->getPaginator();

	Assert::same(20, $paginator->getItemsPerPage());
	Assert::same(3, $paginator->getPage());
});
