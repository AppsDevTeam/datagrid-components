<?php declare(strict_types = 1);

namespace ADT\Datagrid\Model\Filter;

use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Filter\OneColumnFilter;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use UnexpectedValueException;

class FilterCheckbox extends OneColumnFilter
{

	protected array $attributes = [
		'class' => ['form-check-input'],
	];

	protected ?string $template = 'datagrid_filter_checkbox.latte';

	protected ?string $type = 'checkbox';

	public function __construct(
		Datagrid $grid,
		string $key,
		string $name,
		string $column
	)
	{
		parent::__construct($grid, $key, $name, $column);
	}

	public function addToFormContainer(Container $container): void
	{
		$form = $container->lookup(Form::class);

		if (!$form instanceof Form) {
			throw new UnexpectedValueException();
		}

		$translator = $form->getTranslator();

		if ($translator === null) {
			throw new UnexpectedValueException();
		}

		$this->addControl($container, $this->key, $this->name);
	}

	public function getValue(): bool
	{
		return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
	}

	protected function addControl(
		Container $container,
		string $key,
		string $name
	): BaseControl
	{
		$input = $container->addCheckbox($key, $name)
			->setDefaultValue(true)
			->setHtmlAttribute('data-autosubmit-change', true);

		$this->addAttributes($input);

		return $input;
	}

}
