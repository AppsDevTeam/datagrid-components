<?php

declare(strict_types=1);

use ADT\Datagrid\Filter\FilterPeriod;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Exception\DatagridException;
use Nette\Localization\Translator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

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
	$grid = new Datagrid();
	$grid->setTranslator($translator);

	return new FilterPeriod($grid, 'createdAtPeriod', 'period', 'createdAt');
};

$diffInDays = function (DateTimeInterface $from): int {
	return (int) round((new DateTimeImmutable())->getTimestamp() - $from->getTimestamp()) / 86400;
};

// vychozi obdobi je mesic a plati i bez vyplneneho filtru
test('default period', function () use ($createFilter, $diffInDays) {
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
