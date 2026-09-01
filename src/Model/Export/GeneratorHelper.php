<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Export;

use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Column\ColumnDateTime;
use Contributte\Datagrid\Row;

/** @internal spolecna rekonstrukce sloupcu/radku ze serializovaneho tvaru */
final class GeneratorHelper
{
	public static function buildRowsAndColumns(array $items, array $columns): array
	{
		$datagrid = new Datagrid();

		$cols = [];
		foreach ($columns as $key => $def) {
			$cols[] = $column = new $def['class']($datagrid, $key, $def['column'], $def['name']);
			if ($column instanceof ColumnDateTime) {
				$column->setFormat('j. n. Y G:i');
			}
		}

		$rows = [];
		foreach ($items as $item) {
			$rows[] = new Row($datagrid, $item, $datagrid->getPrimaryKey());
		}

		return [$rows, $cols];
	}

	/** Radky agregatove sekce (pole poli) vs. nactene entity */
	public static function isRawRows(array $items): bool
	{
		return $items !== [] && is_array(reset($items));
	}

	/**
	 * Zaklad nazvu exportu z gridName ("Portal:Backoffice:Product-productGrid"
	 * -> "product"). Pouziva se pro nazev souboru, nazev sheetu i nazev sekce
	 * v auditu, aby vsechny tri nesly jedno jmeno.
	 */
	public static function baseName(string $identifier): string
	{
		// posledni CamelCase slovo pryc (historicke chovani normalizeGridName)
		return preg_replace('/[A-Z][a-z]*$/', '', explode('-', $identifier)[1] ?? $identifier) ?: $identifier;
	}

	public static function fileName(string $identifier): string
	{
		return self::baseName($identifier) . '_' . date('Y-m-d_H-i');
	}
}
