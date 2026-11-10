<?php

declare(strict_types=1);

use ADT\Datagrid\Component\BaseGrid;
use Tester\Assert;

/**
 * Pozice radku po pretazeni (BaseGrid::handleSortRows). Pozici pak prepocita Gedmo Sortable: pri presunu v ramci
 * skupiny posune radky mezi starou a novou pozici o jedno misto, pri presunu do jine skupiny radek ze stare skupiny
 * vyjme a do nove vlozi na danou pozici. Testy kontroluji, ze po prepoctu sedi poradi s tim, kam uzivatel radek pustil.
 */

require __DIR__ . '/bootstrap.php';


function sortRowsPosition(int $itemPosition, ?int $previousPosition, ?int $nextPosition, bool $groupChanged): int
{
	return Closure::bind(
		fn () => BaseGrid::getSortRowsPosition($itemPosition, $previousPosition, $nextPosition, $groupChanged),
		null,
		BaseGrid::class,
	)();
}


/**
 * Presun v ramci skupiny podle Gedmo Sortable: radek se vyjme a vlozi na novou pozici, ostatni se posunou.
 */
function gedmoMove(array $rows, string $row, int $position): array
{
	$rows = array_values(array_diff($rows, [$row]));
	array_splice($rows, min($position, count($rows)), 0, [$row]);
	return $rows;
}


/**
 * Poradi, ktere vidi uzivatel po pretazeni radku z indexu $from na index $to.
 */
function dragged(array $rows, int $from, int $to): array
{
	$row = $rows[$from];
	array_splice($rows, $from, 1);
	array_splice($rows, $to, 0, [$row]);
	return $rows;
}


test('presun nahoru i dolu v ramci skupiny skonci tam, kam ho uzivatel pustil', function () {
	$rows = ['A', 'B', 'C', 'D', 'E'];
	foreach (array_keys($rows) as $from) {
		foreach (array_keys($rows) as $to) {
			$expected = dragged($rows, $from, $to);
			$previousPosition = $to > 0 ? array_search($expected[$to - 1], $rows, true) : null;
			$nextPosition = $to === 0 && isset($expected[1]) ? array_search($expected[1], $rows, true) : null;

			$position = sortRowsPosition($from, $previousPosition, $nextPosition, false);

			Assert::same($expected, gedmoMove($rows, $rows[$from], $position), "$from -> $to");
		}
	}
});


test('presun dolu za predchozi radek nepreskoci o misto dal', function () {
	// A (0) za C (2): Gedmo posune B a C nahoru, A patri na pozici 2, ne 3
	Assert::same(2, sortRowsPosition(0, 2, null, false));
});


test('presun nahoru za predchozi radek', function () {
	// E (4) za A (0)
	Assert::same(1, sortRowsPosition(4, 0, null, false));
});


test('presun na zacatek pred nasledujici radek', function () {
	// C (2) pred A (0)
	Assert::same(0, sortRowsPosition(2, null, 0, false));
});


test('presun do jine skupiny skonci tam, kam ho uzivatel pustil', function () {
	$target = ['K', 'L', 'M'];
	foreach ([0, 3, 7] as $itemPosition) {
		foreach (range(0, count($target)) as $to) {
			$expected = $target;
			array_splice($expected, $to, 0, ['X']);
			$previousPosition = $to > 0 ? $to - 1 : null;
			$nextPosition = $to === 0 ? 0 : null;

			$position = sortRowsPosition($itemPosition, $previousPosition, $nextPosition, true);

			Assert::same($expected, gedmoMove($target, 'X', $position), "z pozice $itemPosition na index $to");
		}
	}
});


test('presun do prazdne skupiny', function () {
	Assert::same(0, sortRowsPosition(5, null, null, true));
});


test('pozice neni zaporna', function () {
	Assert::same(0, sortRowsPosition(0, null, -1, false));
	Assert::same(0, sortRowsPosition(3, null, -1, true));
});
