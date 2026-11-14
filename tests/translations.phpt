<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/**
 * Kazdy klic, ktery knihovna preklada (v PHP i v sablonach), musi mit preklad v cs i en - jinak
 * by ho musel dodavat kazdy projekt sam (driv app.forms.global.isActive.* a app.grids.flashes.cantDelete
 * v app.cs.yml projektu). Domena contributte_datagrid: filtr s vyberem vice hodnot z contributte/datagrid 7.
 */

/** @return array<string, string> plochy soubor `klic: hodnota` */
function loadCatalog(string $file): array
{
	$catalog = [];
	foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
		if (preg_match('~^([a-z_.]+):\s*(.*)$~', $line, $m)) {
			$catalog[$m[1]] = trim($m[2], '"');
		}
	}
	return $catalog;
}

/** @return list<string> klice domeny pouzite ve zdrojich knihovny (bez skladanych `period.` . $x) */
function usedKeys(string $domain): array
{
	$keys = [];
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../src'));
	foreach ($files as $file) {
		if (preg_match('~\.(php|latte)$~', $file->getFilename())) {
			preg_match_all("~'" . $domain . "\.([a-z_.]*[a-z_])'~", file_get_contents($file->getPathname()), $m);
			array_push($keys, ...$m[1]);
		}
	}
	return array_values(array_unique($keys));
}


test('klice gridu maji preklad v cs i en', function () {
	$keys = usedKeys('ublaboo_datagrid');
	Assert::contains('is_active.label', $keys);
	Assert::contains('delete.in_use', $keys);

	foreach (['cs', 'en'] as $lang) {
		$catalog = loadCatalog(__DIR__ . "/../src/lang/ublaboo_datagrid.$lang.yml");
		foreach ($keys as $key) {
			Assert::true(isset($catalog[$key]), "ublaboo_datagrid.$key chybi v $lang");
		}
	}
});


test('filtr s vyberem vice hodnot z contributte/datagrid 7', function () {
	Assert::same('Vyberte', loadCatalog(__DIR__ . '/../src/lang/contributte_datagrid.cs.yml')['multiselect_choose']);
	Assert::same('{0} selected', loadCatalog(__DIR__ . '/../src/lang/contributte_datagrid.en.yml')['multiselect_selected']);
});
