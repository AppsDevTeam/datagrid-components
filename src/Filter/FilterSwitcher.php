<?php declare(strict_types = 1);

namespace ADT\Datagrid\Filter;

use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;

class FilterSwitcher extends FilterCheckbox
{
	protected ?string $template = 'datagrid_filter_switcher.latte';

	protected function addControl(
		Container $container,
		string $key,
		string $name
	): BaseControl
	{
		$input = parent::addControl($container, $key, $name);

		$input
			->setHtmlAttribute('type', 'checkbox')
			->setHtmlAttribute('role', 'switch');

		return $input;
	}
}
