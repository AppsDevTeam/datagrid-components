<?php

declare(strict_types=1);

use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Filter\FilterPeriod;
use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\QueryObjectDataSource\QueryObjectDataSource;
use Contributte\Datagrid\Datagrid as ContributteDatagrid;
use Contributte\Datagrid\Exception\DatagridException;
use Nette\Application\Request;
use Nette\Application\UI\Presenter;
use Nette\Localization\Translator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/**
 * Query objekt, ktery se nikdy nespusti - sbira jen podminky z by(), ktere do nej grid poslal.
 */
class PeriodTestQuery extends QueryObject
{
	public function getEntityClass(): string
	{
		return stdClass::class;
	}

	protected function setDefaultOrder(): void
	{
	}
}

/**
 * Preklad vraci klic, takze porovnani popisku v getPeriod() zustava konzistentni
 * s tim, co vraci getDefaultRangeText().
 */
$translator = new class implements Translator {
	public function translate(string|\Stringable $message, mixed ...$parameters): string
	{
		return (string) $message;
	}
};

$createFilter = function () use ($translator): FilterPeriod {
	$grid = new ContributteDatagrid();
	$grid->setTranslator($translator);

	return new FilterPeriod($grid, 'createdAtPeriod', 'period', 'createdAt');
};

/**
 * Grid pripojeny k presenteru, protoze addFilterPeriod() cte POST z requestu.
 *
 * @param array<string, mixed> $filterState stav persistentniho parametru `filter` pred pridanim filtru
 * @param array<string, mixed> $post
 */
$createGrid = function (array $filterState = [], array $post = []) use ($translator): DataGrid {
	$presenter = new class extends Presenter {};
	(new ReflectionProperty(Presenter::class, 'request'))->setValue(
		$presenter,
		new Request('Test', $post ? 'POST' : 'GET', [], $post),
	);

	$grid = new DataGrid();
	$grid->setTranslator($translator);
	$presenter->addComponent($grid, 'grid');
	$grid->addFilterText('url', 'url');

	(new ReflectionProperty($grid, 'filter'))->setValue($grid, $filterState);

	return $grid;
};

$setFilterState = function (DataGrid $grid, array $filterState): void {
	(new ReflectionProperty($grid, 'filter'))->setValue($grid, $filterState);
};

$getFilterState = function (DataGrid $grid): array {
	return (new ReflectionProperty($grid, 'filter'))->getValue($grid);
};

/**
 * Projde stejnou cestou jako nacteni dat gridu a vrati podminky na sloupec obdobi.
 *
 * @return list<array{column: string|array, value: mixed, mode: QueryObjectByMode}>
 */
$getPeriodConditions = function (DataGrid $grid): array {
	$query = (new ReflectionClass(PeriodTestQuery::class))->newInstanceWithoutConstructor();

	(new QueryObjectDataSource($query))->filter($grid->assembleFilters());

	$conditions = [];
	foreach ((new ReflectionProperty(QueryObject::class, 'filter'))->getValue($query) as $_closure) {
		$_used = (new ReflectionFunction($_closure))->getClosureUsedVariables();

		if ($_used['column'] === 'createdAt') {
			$conditions[] = $_used;
		}
	}

	return $conditions;
};

$assertLimitedFrom = function (array $conditions, string $modify): void {
	Assert::count(1, $conditions);
	Assert::same(QueryObjectByMode::BETWEEN, $conditions[0]['mode']);
	Assert::same(
		(new DateTimeImmutable())->modify($modify)->format('Y-m-d'),
		$conditions[0]['value'][0]->format('Y-m-d'),
	);
	Assert::null($conditions[0]['value'][1]);
};


// vychozi obdobi filtru je mesic, i kdyz do nej nikdo nesahl
test('default period', function () use ($createFilter) {
	$filter = $createFilter();

	Assert::same(FilterPeriod::MONTH, $filter->getDefaultPeriod());
	Assert::same('ublaboo_datagrid.period.month', $filter->getDefaultRangeText());
	Assert::false($filter->isAlwaysApplied());

	[$from, $to] = $filter->getRange();
	Assert::null($to);
	Assert::same(
		(new DateTimeImmutable())->modify('-1 month')->format('Y-m-d'),
		$from->format('Y-m-d'),
	);
});

// kvuli komprimovanym chunkum v logdb chceme na logovacich gridech 3 mesice
test('quarter as default period', function () use ($createFilter) {
	$filter = $createFilter()->setDefaultPeriod(FilterPeriod::QUARTER);

	Assert::same(FilterPeriod::QUARTER, $filter->getDefaultPeriod());
	Assert::same('ublaboo_datagrid.period.quarter', $filter->getDefaultRangeText());

	[$from, $to] = $filter->getRange();
	Assert::null($to);
	Assert::same(
		(new DateTimeImmutable())->modify('-3 months')->format('Y-m-d'),
		$from->format('Y-m-d'),
	);
});

// vlastni rozsah neni obdobi, nesmi se prebit vychozi hodnotou
test('custom range wins over the default period', function () use ($createFilter) {
	$filter = $createFilter()->setDefaultPeriod(FilterPeriod::QUARTER);
	$filter->setValue(['range' => '1.1.2026 - 31.1.2026']);

	Assert::same(FilterPeriod::CUSTOM, $filter->getPeriod());

	[$from, $to] = $filter->getRange();
	Assert::same('2026-01-01 00:00:00', $from->format('Y-m-d H:i:s'));
	Assert::same('2026-01-31 23:59:59', $to->format('Y-m-d H:i:s'));
});

test('always apply flag', function () use ($createFilter) {
	$filter = $createFilter()->setAlwaysApply();
	Assert::true($filter->isAlwaysApplied());

	Assert::false($createFilter()->setAlwaysApply(false)->isAlwaysApplied());
});

// custom neni obdobi a nesmysl uz vubec ne, jinak by getRange() spadl na nedefinovanem klici
test('invalid default period', function () use ($createFilter) {
	Assert::exception(
		fn () => $createFilter()->setDefaultPeriod(FilterPeriod::CUSTOM),
		DatagridException::class,
	);

	Assert::exception(
		fn () => $createFilter()->setDefaultPeriod('century'),
		DatagridException::class,
	);
});


// bez $alwaysApply se chovani nemeni - samotny vypis bez hledani obdobi neomezuje
test('grid without always apply does not limit plain listing', function () use ($createGrid, $getPeriodConditions) {
	$grid = $createGrid();
	$filter = $grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt');

	Assert::false($filter->isAlwaysApplied());
	Assert::same([], $getPeriodConditions($grid));
});

test('grid without always apply limits the search', function () use ($createGrid, $getPeriodConditions, $assertLimitedFrom) {
	$grid = $createGrid(['url' => 'orders']);
	$grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt');

	$assertLimitedFrom($getPeriodConditions($grid), '-1 month');
});

// grid filtrovany az v query objektu (detail entity, modal) zadny aktivni filtr nema
test('grid with always apply limits plain listing', function () use ($createGrid, $getPeriodConditions, $assertLimitedFrom) {
	$grid = $createGrid();
	$filter = $grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt', alwaysApply: true);

	Assert::true($filter->isAlwaysApplied());
	$assertLimitedFrom($getPeriodConditions($grid), '-1 month');
});

test('grid with always apply limits the search too', function () use ($createGrid, $getPeriodConditions, $assertLimitedFrom) {
	$grid = $createGrid(['url' => 'orders']);
	$grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt', alwaysApply: true);

	$assertLimitedFrom($getPeriodConditions($grid), '-1 month');
});

// predplneny text v poli musi odpovidat obdobi, ktere se opravdu uplatni
test('grid prefills the configured default period', function () use ($createGrid, $getFilterState, $getPeriodConditions, $assertLimitedFrom) {
	$grid = $createGrid();
	$filter = $grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt', FilterPeriod::QUARTER, true);

	Assert::same(FilterPeriod::QUARTER, $filter->getDefaultPeriod());
	Assert::same(['range' => 'ublaboo_datagrid.period.quarter'], $getFilterState($grid)['createdAtPeriod']);
	$assertLimitedFrom($getPeriodConditions($grid), '-3 months');
});

// obdobi vybrane uzivatelem se vychozim neprepisuje
test('grid keeps the period picked by the user', function () use ($createGrid, $getFilterState, $getPeriodConditions, $assertLimitedFrom) {
	$grid = $createGrid(['createdAtPeriod' => ['range' => 'ublaboo_datagrid.period.day']]);
	$grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt', FilterPeriod::QUARTER, true);

	Assert::same(['range' => 'ublaboo_datagrid.period.day'], $getFilterState($grid)['createdAtPeriod']);
	$assertLimitedFrom($getPeriodConditions($grid), '-1 day');
});

test('grid keeps the custom range picked by the user', function () use ($createGrid, $getPeriodConditions) {
	$grid = $createGrid(['createdAtPeriod' => ['range' => '1.1.2026 - 31.1.2026']]);
	$grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt', FilterPeriod::QUARTER, true);

	$conditions = $getPeriodConditions($grid);
	Assert::count(1, $conditions);
	Assert::same('2026-01-01 00:00:00', $conditions[0]['value'][0]->format('Y-m-d H:i:s'));
	Assert::same('2026-01-31 23:59:59', $conditions[0]['value'][1]->format('Y-m-d H:i:s'));
});

// filtr bez hodnoty datagrid vubec neuplatni - prazdna hodnota z URL nesmi omezeni zrusit
test('grid with always apply falls back to the default period on empty value', function () use ($createGrid, $getFilterState, $getPeriodConditions, $assertLimitedFrom) {
	foreach ([['range' => ''], ['range' => null], [], '', null] as $_empty) {
		$grid = $createGrid(['createdAtPeriod' => $_empty]);
		$grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt', FilterPeriod::QUARTER, true);

		$assertLimitedFrom($getPeriodConditions($grid), '-3 months');
		Assert::same(['range' => 'ublaboo_datagrid.period.quarter'], $getFilterState($grid)['createdAtPeriod']);
	}
});

// pri odeslani formulare se predplneni preskoci a prazdnou hodnotu do stavu zapise az zpracovani formulare
test('grid with always apply falls back to the default period on empty submitted value', function () use ($createGrid, $setFilterState, $getPeriodConditions, $assertLimitedFrom) {
	$grid = $createGrid([], ['filter' => ['createdAtPeriod' => ['range' => '']]]);
	$grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt', FilterPeriod::QUARTER, true);
	$setFilterState($grid, ['createdAtPeriod' => ['range' => '']]);

	$assertLimitedFrom($getPeriodConditions($grid), '-3 months');
});

// bez $alwaysApply zustava prazdna hodnota prazdna, jako doted
test('grid without always apply keeps empty value', function () use ($createGrid, $getFilterState, $getPeriodConditions) {
	$grid = $createGrid(['createdAtPeriod' => ['range' => '']]);
	$grid->addFilterPeriod('createdAtPeriod', 'period', 'createdAt');

	Assert::same([], $getPeriodConditions($grid));
	Assert::same(['range' => ''], $getFilterState($grid)['createdAtPeriod']);
});

test('grid rejects invalid default period', function () use ($createGrid) {
	Assert::exception(
		fn () => $createGrid()->addFilterPeriod('createdAtPeriod', 'period', 'createdAt', FilterPeriod::CUSTOM),
		DatagridException::class,
	);
});
