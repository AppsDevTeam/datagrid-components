<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/**
 * Akce v rozbalovacim menu radku musi respektovat podminku allowRowsAction() sve vlastni
 * akce. Filtr v sablone driv bral $key z okoli sablony (klic z predchozi smycky), takze
 * se vsem akcim overovala podminka jedne jine akce.
 */

$template = file_get_contents(__DIR__ . '/../src/Component/DataGrid.latte');
preg_match('~\{var \$dropdownActions = (array_filter\(.+?\))\}~', $template, $match);
$filter = $match[1] ?? null;

$action = fn (string $class) => new class ($class) {
	public function __construct(private string $class)
	{
	}

	public function getClass($row): string
	{
		return $this->class;
	}

	public function shouldBeRendered($row): bool
	{
		return true;
	}
};

// Radek povoli jen akci "pair", "disconnect" ma podminku nesplnenou
$row = new class {
	public function hasAction(string $key): bool
	{
		return $key === 'pair';
	}
};

test('sablona vybira akce do menu filtrem', function () use ($filter) {
	Assert::notNull($filter);
});


test('akce do menu se filtruji podle podminky radku pro vlastni klic', function () use ($filter, $action, $row) {
	$actions = ['pair' => $action('ajax'), 'disconnect' => $action('ajax'), 'edit' => $action('ajax datagrid-edit')];
	// Klic, ktery v sablone zbyl z predchozi smycky - filtr na nem nesmi zaviset
	$key = 'pair';

	$dropdownActions = eval('return ' . $filter . ';');

	Assert::same(['pair'], array_keys($dropdownActions));
});
