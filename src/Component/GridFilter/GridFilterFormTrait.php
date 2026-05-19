<?php

namespace ADT\Datagrid\Component\GridFilter;

use ADT\Datagrid\Component\BaseGrid;
use ADT\Datagrid\Component\DataGrid;
use ADT\Datagrid\Model\Entities\GridFilter;
use ADT\Datagrid\Model\Queries\GridFilterQuery;
use ADT\DoctrineComponents\EntityManager;
use ADT\Forms\StaticContainer;
use Exception;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\IComponent;
use Nette\Forms\Controls\HiddenField;
use Nette\Forms\Form;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use ReflectionException;

trait GridFilterFormTrait
{
	abstract protected function getEntityManager(): EntityManager;
	abstract protected function getGridFilterQuery(): GridFilterQuery;
	abstract public function lookup(?string $type, bool $throw = true): ?IComponent;
	abstract public function redrawControl(?string $snippet = null, bool $redraw = true): void;
	abstract public function getPresenter(): ?Presenter;

	const array TRANSLATIONS = [
		'sEqual' => 'ublaboo_datagrid.advanced_search.operator.equal',
		'sNotEqual' => 'ublaboo_datagrid.advanced_search.operator.not_equal',
		'sStart' => 'ublaboo_datagrid.advanced_search.operator.starts_with',
		'sContain' => 'ublaboo_datagrid.advanced_search.operator.contains',
		'sNotContain' => 'ublaboo_datagrid.advanced_search.operator.not_contains',
		'sFinish' => 'ublaboo_datagrid.advanced_search.operator.ends_with',
		'sInList' => 'ublaboo_datagrid.advanced_search.operator.in_list',
		'sIsNull' => 'ublaboo_datagrid.advanced_search.operator.is_null',
		'sIsNotNull' => 'ublaboo_datagrid.advanced_search.operator.is_not_null',
		'sBefore' => 'ublaboo_datagrid.advanced_search.operator.before',
		'sAfter' => 'ublaboo_datagrid.advanced_search.operator.after',
		'sNumEqual' => 'ublaboo_datagrid.advanced_search.operator.num_equal',
		'sNumNotEqual' => 'ublaboo_datagrid.advanced_search.operator.num_not_equal',
		'sGreater' => 'ublaboo_datagrid.advanced_search.operator.greater',
		'sSmaller' => 'ublaboo_datagrid.advanced_search.operator.smaller',
		'sOn' => 'ublaboo_datagrid.advanced_search.operator.on',
		'sNotOn' => 'ublaboo_datagrid.advanced_search.operator.not_on',
		'sAt' => 'ublaboo_datagrid.advanced_search.operator.at',
		'sNotAt' => 'ublaboo_datagrid.advanced_search.operator.not_at',
		'sBetween' => 'ublaboo_datagrid.advanced_search.operator.between',
		'sNotBetween' => 'ublaboo_datagrid.advanced_search.operator.not_between',
		'opAnd' => 'ublaboo_datagrid.advanced_search.operator.and',
		'yes' => 'ublaboo_datagrid.yes',
		'no' => 'ublaboo_datagrid.no',
		'bNewCond' => 'ublaboo_datagrid.advanced_search.add_filter',
		'bAddCond' => 'ublaboo_datagrid.advanced_search.add_condition',
		'bUpdateCond' => 'ublaboo_datagrid.advanced_search.update_condition',
		'bSubmit' => 'ublaboo_datagrid.advanced_search.btn_submit',
		'bCancel' => 'ublaboo_datagrid.advanced_search.btn_cancel',
	];

	const array EVO_API = [
		'sEqual' => 'eq',
		'sNotEqual' => 'ne',
		'sStart' => 'sw',
		'sContain' => 'ct',
		'sNotContain' => 'nct',
		'sFinish' => 'fw',
		'sInList' => 'in',
		'sIsNull' => 'null',
		'sIsNotNull' => 'nn',
		'sGreater' => 'gt',
		'sSmaller' => 'lt',
		'sBetween' => 'bw',
		'sNotBetween' => 'nbw',
	];

	const string F_TYPE_TEXT = 'text';
	const string F_TYPE_BOOL = 'bool';
	const string F_TYPE_NUMBER = 'number';
	const string F_TYPE_DATE = 'date';
	const string F_TYPE_TIME = 'time';
	const string F_TYPE_LIST = 'list';
	const string F_TYPE_JSON = 'json';
	const string F_TYPE_LIST_OPTS = 'listOpts';
	const string F_TYPE_LIST_DROPDOWN = 'listDropdown';

	const array F_TYPES = [
		self::F_TYPE_TEXT => 'text',
		self::F_TYPE_BOOL => 'boolean',
		self::F_TYPE_NUMBER => 'number',
		self::F_TYPE_DATE => 'date',
		self::F_TYPE_TIME => 'time',
		self::F_TYPE_LIST => 'list',
		self::F_TYPE_JSON => 'json',
		self::F_TYPE_LIST_OPTS => 'list-options',
		self::F_TYPE_LIST_DROPDOWN => 'list-dropdown',
	];

	protected BaseGrid $grid;

	/**
	 * @throws Exception
	 */
	public function initForm(\ADT\DoctrineForms\Form $form, ?GridFilter $gridFilter): void
	{
		ini_set('memory_limit', '1G');

		$defaults = [];
		if (!$gridFilter) {
			$defaults['value'] = !empty($this->grid->getGrid()->getParameters()['filter']['advancedSearch'])
				? Json::decode($this->grid->getGrid()->getParameters()['filter']['advancedSearch'], forceArrays: true)
				: [];
		}

		$filterList = [];
		foreach ($this->grid->getGrid()->getGridFilterFields() as $item) {
			$filterList[$item['id']] = $item;
		}

		$form->addDynamicContainer('value', function (StaticContainer $container) use ($form, $filterList, $gridFilter, $defaults) {
			$columnItems = [];

			foreach ($filterList as $filter) {
				if (!isset($filter['label'])) {
					continue;
				}
				$columnItems[$filter['id']] = $filter['label'];
			}

			$values = $form->getValidatedValues('array');
			$usedLabels = array_column($values['value'], 'label');
			$columnItems = array_diff_key($columnItems, array_flip($usedLabels));
			unset ($columnItems['isActive']);
			$container->addSelect('label', '', $columnItems)
				->setRequired()
				->setPrompt('---');

			if ($gridFilter) {
				$form->mapToForm();
			} else {
				$form->setDefaults($defaults);
			}

			$selectedType = $filterList[$container->getUntrustedValues()->label]['type'] ?? null;

			$container->addSection(function() use ($container, $gridFilter, $form, $defaults, $filterList, $selectedType) {
				switch ($selectedType) {
					case null:
						$container->addHidden('operator');
						break;

					case 'select':
					case 'checkbox':
						$container->addHidden('operator')
							->setValue(self::EVO_API['sEqual']);
						break;

					case 'multi-select':
						$container->addHidden('operator')
							->setValue(self::EVO_API['sInList']);
						break;

					case 'date-range':
						$operatorItems[self::EVO_API['sIsNull']] = self::TRANSLATIONS['sIsNull'];
						$operatorItems[self::EVO_API['sIsNotNull']] = self::TRANSLATIONS['sIsNotNull'];
						$operatorItems[self::EVO_API['sSmaller']] = self::TRANSLATIONS['sSmaller'];
						$operatorItems[self::EVO_API['sGreater']] = self::TRANSLATIONS['sGreater'];
						$operatorItems[self::EVO_API['sBetween']] = self::TRANSLATIONS['sBetween'];
						$operatorItems[self::EVO_API['sNotBetween']] = self::TRANSLATIONS['sNotBetween'];
						$container->addSelect('operator', null, $operatorItems)
							->setPrompt('---')
							->setRequired();
						break;

					case 'range':
					case 'date':
						$operatorItems[self::EVO_API['sEqual']] = self::TRANSLATIONS['sEqual'];
						$operatorItems[self::EVO_API['sNotEqual']] = self::TRANSLATIONS['sNotEqual'];
						$operatorItems[self::EVO_API['sIsNull']] = self::TRANSLATIONS['sIsNull'];
						$operatorItems[self::EVO_API['sIsNotNull']] = self::TRANSLATIONS['sIsNotNull'];
						$operatorItems[self::EVO_API['sSmaller']] = self::TRANSLATIONS['sSmaller'];
						$operatorItems[self::EVO_API['sGreater']] = self::TRANSLATIONS['sGreater'];
						$operatorItems[self::EVO_API['sBetween']] = self::TRANSLATIONS['sBetween'];
						$operatorItems[self::EVO_API['sNotBetween']] = self::TRANSLATIONS['sNotBetween'];
						$container->addSelect('operator', null, $operatorItems)
							->setPrompt('---')
							->setRequired();
						break;

					case 'text':
						$operatorItems[self::EVO_API['sEqual']] = self::TRANSLATIONS['sEqual'];
						$operatorItems[self::EVO_API['sNotEqual']] = self::TRANSLATIONS['sNotEqual'];
						$operatorItems[self::EVO_API['sStart']] = self::TRANSLATIONS['sStart'];
						$operatorItems[self::EVO_API['sContain']] = self::TRANSLATIONS['sContain'];
						$operatorItems[self::EVO_API['sNotContain']] = self::TRANSLATIONS['sNotContain'];
						$operatorItems[self::EVO_API['sFinish']] = self::TRANSLATIONS['sFinish'];
						$operatorItems[self::EVO_API['sInList']] = self::TRANSLATIONS['sInList'];
						$operatorItems[self::EVO_API['sIsNull']] = self::TRANSLATIONS['sIsNull'];
						$operatorItems[self::EVO_API['sIsNotNull']] = self::TRANSLATIONS['sIsNotNull'];
						$container->addSelect('operator', null, $operatorItems)
							->setPrompt('---')
							->setRequired();
						break;

					default:
						throw new Exception('Unknown filter type: ' . $selectedType);
				}
			}, name: 'operator', watchForRedraw: [$container['label']]);

			$container->addSection(function () use ($container, $filterList, $selectedType) {
				if ($container['label']->getValue()) {
					if ($operatorValue = $container['operator']->getValue()) {
						if ($operatorValue === self::EVO_API['sIsNull'] || $operatorValue === self::EVO_API['sIsNotNull'] || !$operatorValue) {
							$container->addHidden('value', '');
						} else {
							$isBetween = $operatorValue === self::EVO_API['sBetween'] || $operatorValue === self::EVO_API['sNotBetween'];

							switch ($selectedType) {
								case 'checkbox':
									$container->addSelect('value', null, [
										1 => self::TRANSLATIONS['yes'],
										0 => self::TRANSLATIONS['no'],
									])
										->setPrompt('---');
									break;

								case 'select':
									$container->addSelect(
										'value',
										null,
										$this->parseListItems($filterList[$container['label']->getValue()]['list'])
									)->setPrompt('---');
									break;

								case 'multi-select':
									$container->addMultiSelect(
										'value',
										null,
										$this->parseListItems($filterList[$container['label']->getValue()]['list'])
									);
									break;

								case 'date':
									$container->addDate('value')
										->setRequired();
									if ($isBetween) {
										$container->addDate('value2')
											->setRequired();
									}
									break;

								case 'date-range':
									$container->addDateTime('value')
										->setRequired();
									if ($isBetween) {
										$container->addDateTime('value2')
											->setRequired();
									}
									break;

								case 'range':
									$container->addText('value')
										->setRequired();
									if ($isBetween) {
										$container->addText('value2')
											->setRequired();
									}
									break;

								case 'text':
									$container->addText('value')
										->setRequired();
									break;

								default:
									throw new Exception('Unknown filter type: ' . $selectedType);
							}
						}
					}
				} else {
					$container->addHidden('value');
				}
			}, name: 'value', watchForRedraw: [$container['label'], $container['operator']]);

		}, isRequiredMessage: $form->getTranslator()->translate('ublaboo_datagrid.advanced_search.required_filter'));

		$form->addCheckbox('save', 'ublaboo_datagrid.advanced_search.save');
		if ($gridFilter) {
			$form['save']->setDefaultValue(1);
		}

		$form->addSection(function() use ($form) {
			$form->addText('name', 'ublaboo_datagrid.advanced_search.name')
				->addConditionOn($form['save'], Form::Equal, true)
					->setRequired();
		}, name: 'name');
		$form['save']->addCondition(Form::Equal, true)
			->toggle($form->getSections()['name']->getHtmlId());

		$form->addSubmit("submit", "ublaboo_datagrid.advanced_search.submit");
		$form->addSubmit('addFilter')
			->setValidationScope([])
			->onClick[] = function () use ($form) {
				$form['value']->createNew();
				$this->redrawControl('items');
			};

		if (!$gridFilter) {
			$form->setDefaults($defaults);
		}
	}

	public function validateForm(\ADT\DoctrineForms\Form $form, array $inputs, ?GridFilter $gridFilter): void
	{
		$gridFilterQuery = $this->getGridFilterQuery()
			->byName($inputs['name'])
			->byGrid($this->grid->getGridName());

		if ($gridFilter) {
			$gridFilterQuery->byIdNot($gridFilter->getId());
		}

		if ($gridFilterQuery->count() > 0) {
			$form->addError(sprintf($form->getTranslator()->translate('ublaboo_datagrid.advanced_search.name_already_used'), $inputs['name']), translate: false);
		}

		if (!count($inputs['value'])) {
			$form->addError('ublaboo_datagrid.advanced_search.no_filter_selected');
		}
	}

	protected function createEntity(): ?GridFilter
	{
		return null;
	}

	/**
	 * @throws JsonException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function processForm(?GridFilter $gridFilter, array $inputs): void
	{
		$filters = $this->grid->getGrid()->getParameters()['filter'];
		if ($inputs['save']) {
			if (!$gridFilter) {
				/** @var GridFilter $gridFilter */
				$gridFilter = new ($this->getEntityClass());
				if (method_exists($this, 'initEntity')) {
					$this->initEntity($gridFilter);
				}
				$gridFilter->setGrid($this->grid->getGridName());
				$gridFilter->setValue($inputs['value']);
				$gridFilter->setName($inputs['name']);
				$this->getEntityManager()->persist($gridFilter);
			}
			$this->getEntityManager()->flush();
			unset($filters['advancedSearch']);
			$filters = array_merge($filters, [DataGrid::SELECTED_GRID_FILTER_KEY => $gridFilter->getId()]);
			$this->grid->getGrid()['filter']['filter'][DataGrid::SELECTED_GRID_FILTER_KEY]->setItems($this->grid->getGrid()->getAdvancedFilterItems());
		} else {
			unset($filters[DataGrid::SELECTED_GRID_FILTER_KEY]);
			$filters = array_merge($filters, ['advancedSearch' => Json::encode($inputs['value'])]);
		}

		$grid = $this->grid->getGrid();
		$grid->setFilter($filters);
		$grid->handleRefreshState();
		$grid->redrawControl(redraw: false);
	}

	protected function parseListItems(array $list): array
	{
		$return = [];

		foreach ($list as $item) {
			$return[$item['id']] = $item['label'];
		}

		return $return;
	}

	protected function getTemplateFile(): ?string
	{
		return __DIR__ . '/GridFilterForm.latte';
	}

	/**
	 * @throws ReflectionException
	 */
	protected function getEntityClass(): string
	{
		return $this->getEntityManager()->findEntityClassByInterface(GridFilter::class);
	}

	public function setGrid(BaseGrid $grid): static
	{
		$this->grid = $grid;
		return $this;
	}
}
