<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use Nette\Forms\Controls\BaseControl;

final class FilterInput
{
	/**
	 * @param array<string, string> $attributes atributy pridane k prvku pred vykreslenim
	 */
	public static function render(BaseControl $input, array $attributes = []): string
	{
		$control = $input->getControl();

		foreach ($attributes as $_name => $_value) {
			$control->appendAttribute($_name, $_value);
		}

		return self::encodeAttributes((string) $control);
	}

	private static function encodeAttributes(string $html): string
	{
		return (string) preg_replace_callback(
			'~=(["\'])(.*?)\1~s',
			static fn (array $m): string => '=' . $m[1] . str_replace(['<', '>'], ['&lt;', '&gt;'], $m[2]) . $m[1],
			$html,
		);
	}
}
