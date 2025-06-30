<?php declare(strict_types = 1);

namespace ADT\Datagrid\Filter;

use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Filter\FilterText;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use UnexpectedValueException;

class FilterCheckbox extends FilterText
{

	protected array $attributes = [
		'class' => ['form-check-input'],
	];

	protected ?string $template = 'datagrid_filter_checkbox.latte';

	protected ?string $type = 'checkbox';

	protected bool $defaultValue = false;

	public function __construct(
		Datagrid $grid,
		string $key,
		string $name,
		string $column
	)
	{
		parent::__construct($grid, $key, $name, (array)$column);
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

	public function getValue(): mixed
	{
		if (isset($this->value) && $this->value === 'false') {
			return null;
		}

		return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
	}

	protected function addControl(
		Container $container,
		string $key,
		string $name
	): BaseControl
	{
		$input = $container->addCheckbox($key, $name)
			->setHtmlAttribute('data-autosubmit-change', true);
		$this->addAttributes($input);

		return $input;
	}

	public function setValueSet(bool $valueSet): self
	{
		$this->valueSet = $valueSet;
		return $this;
	}

	public function setDefaultValue(bool $defaultValue): self
	{
		$this->defaultValue = $defaultValue;
		return $this;
	}
}
