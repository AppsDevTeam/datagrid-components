<?php declare(strict_types = 1);

namespace ADT\Datagrid\Filter;

use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;

class FilterSwitcher extends FilterCheckbox
{
	protected ?string $template = 'datagrid_filter_switcher.latte';

	protected bool $inAdvancedFilter = true;

	/**
	 * Prepinac je v radku nad gridem a dialog pokrocileho filtru ho standardne nabizi podruhe jako
	 * ano/ne. Volba "ne" tam ale nic nefiltruje (vypnuty prepinac znamena "bez omezeni", ne opak),
	 * takze kdo to nechce, prepinac z dialogu vyradi.
	 */
	public function setInAdvancedFilter(bool $inAdvancedFilter): static
	{
		$this->inAdvancedFilter = $inAdvancedFilter;
		return $this;
	}

	public function isInAdvancedFilter(): bool
	{
		return $this->inAdvancedFilter;
	}

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
