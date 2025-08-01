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

	const array TRANSLATIONS = [// TODO překlady
		'sEqual' => 'je rovno',
		'sNotEqual' => 'není rovno',
		'sStart' => 'začíná na',
		'sContain' => 'obsahuje',
		'sNotContain' => 'neobsahuje',
		'sFinish' => 'končí na',
		'sInList' => 'je v seznamu',
		'sIsNull' => 'je prázdné',
		'sIsNotNull' => 'není prázdné',
		'sBefore' => 'před',
		'sAfter' => 'po',
		'sNumEqual' => 'je rovno',
		'sNumNotEqual' => 'není rovno',
		'sGreater' => 'je větší',
		'sSmaller' => 'je menší',
		'sOn' => 'rovno',
		'sNotOn' => 'není rovno',
		'sAt' => 'v',
		'sNotAt' => 'není v',
		'sBetween' => 'mezi',
		'sNotBetween' => 'není mezi',
		'opAnd' => 'a',
		'yes' => 'Ano',
		'no' => 'Ne',
		'bNewCond' => 'Přidat filtr',
		'bAddCond' => 'Přidat podmínku',
		'bUpdateCond' => 'Aktualizovat podmínku',
		'bSubmit' => 'Odeslat',
		'bCancel' => 'Zrušit',
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

			$container->addSelect('label', '', $columnItems)
				->setRequired()
				->setPrompt('---');

			if ($gridFilter) {
				$form->mapToForm();
			} else {
				$form->setDefaults($defaults);
			}

			$selectedType = $filterList[$container->getUntrustedValues()->label]['type'] ?? null;

			switch ($selectedType) {
				case self::F_TYPES['list']:
					$container->addHidden('operator', self::EVO_API['sInList'])
						->setValue(self::EVO_API['sInList']);
					break;

				case self::F_TYPES['listOpts']:
				case self::F_TYPES['listDropdown']:
				case self::F_TYPES['bool']:
					$container->addHidden('operator', self::EVO_API['sEqual'])
						->setValue(self::EVO_API['sEqual']);
					break;

				default:
					$operatorItems = [];

					switch ($selectedType) {
						case self::F_TYPES['date']:
						case self::F_TYPES['time']:
							if ($selectedType === self::F_TYPES['time']) {
								$operatorItems[self::EVO_API['sEqual']] = self::TRANSLATIONS['sAt'];
								$operatorItems[self::EVO_API['sNotEqual']] = self::TRANSLATIONS['sNotAt'];
							} else {
								$operatorItems[self::EVO_API['sEqual']] = self::TRANSLATIONS['sOn'];
								$operatorItems[self::EVO_API['sNotEqual']] = self::TRANSLATIONS['sNotOn'];
							}

							$operatorItems[self::EVO_API['sGreater']] = self::TRANSLATIONS['sAfter'];
							$operatorItems[self::EVO_API['sSmaller']] = self::TRANSLATIONS['sBefore'];
							$operatorItems[self::EVO_API['sBetween']] = self::TRANSLATIONS['sBetween'];
							$operatorItems[self::EVO_API['sNotBetween']] = self::TRANSLATIONS['sNotBetween'];
							break;

						case self::F_TYPES['number']:
							$operatorItems[self::EVO_API['sEqual']] = self::TRANSLATIONS['sNumEqual'];
							$operatorItems[self::EVO_API['sNotEqual']] = self::TRANSLATIONS['sNumNotEqual'];
							$operatorItems[self::EVO_API['sGreater']] = self::TRANSLATIONS['sGreater'];
							$operatorItems[self::EVO_API['sSmaller']] = self::TRANSLATIONS['sSmaller'];
							break;

						default:
							$operatorItems[self::EVO_API['sEqual']] = self::TRANSLATIONS['sEqual'];
							$operatorItems[self::EVO_API['sNotEqual']] = self::TRANSLATIONS['sNotEqual'];
							$operatorItems[self::EVO_API['sStart']] = self::TRANSLATIONS['sStart'];
							$operatorItems[self::EVO_API['sContain']] = self::TRANSLATIONS['sContain'];
							$operatorItems[self::EVO_API['sNotContain']] = self::TRANSLATIONS['sNotContain'];
							$operatorItems[self::EVO_API['sFinish']] = self::TRANSLATIONS['sFinish'];
							$operatorItems[self::EVO_API['sInList']] = self::TRANSLATIONS['sInList'];
					}

					$operatorItems[self::EVO_API['sIsNull']] = self::TRANSLATIONS['sIsNull'];
					$operatorItems[self::EVO_API['sIsNotNull']] = self::TRANSLATIONS['sIsNotNull'];
					break;
			}

			if ($container['label']->getValue()) {
				if (isset($operatorItems)) {
					$container->addSelect('operator', '', $operatorItems) // TODO translate
					->setPrompt('---')
						->setRequired();
				}

				$operatorValue = $container['operator']->getValue();

				if ($operatorValue) {
					if (
						$selectedType !== self::F_TYPES['list'] &&
						($operatorValue === self::EVO_API['sIsNull'] || $operatorValue === self::EVO_API['sIsNotNull'])
					) {
						$container->addHidden('value', '');
					} else {
						$isBetween = $operatorValue === self::EVO_API['sBetween'] || $operatorValue === self::EVO_API['sNotBetween'];

						switch ($selectedType) {
							case self::F_TYPES['bool']:
								$container->addSelect('value', null, [
									1 => self::TRANSLATIONS['yes'],
									0 => self::TRANSLATIONS['no'],
								]);
								break;

							case self::F_TYPES['list']:
							case self::F_TYPES['listOpts']:
							case self::F_TYPES['listDropdown']:
								$container->addMultiSelect(
									'value',
									null,
									$this->parseListItems($filterList[$container->getValues()->label]['list'])
								)->setRequired();
								break;

							case self::F_TYPES['date']:
							case self::F_TYPES['time']:
							case self::F_TYPES['number']:
								$type = ($selectedType === self::F_TYPES['date']) ? 'datetime' : 'text';
								$container->{'add' . ucfirst($type)}('value')
									->setRequired();

								if ($isBetween) {
									$container->{'add' . ucfirst($type)}('value2')
										->setRequired();
								}

								break;

							default:
								$container->addText('value')
									->setRequired();

								if ($operatorValue === self::EVO_API['sInList']) {
									$container->addText('delimiter', 'Oddělovač') // TODO překlady
									->setRequired()
										->setHtmlAttribute('placeholder', 'Oddělovač'); // TODO překlady
								}
						}
					}
				}
			} else {
				$container->addHidden('value');
			}
		}, isRequiredMessage: 'Zadejte alespoň 1 filtr.'); // TODO translate

		$form->addCheckbox('save', 'Uložit') // TODO translate
		->addCondition(Form::Equal, true)
			->toggle('name-block');

		if ($gridFilter) {
			$form['save']->setDefaultValue(1);
		}

		$form->addText('name', 'Název')// TODO translate
		->addConditionOn($form['save'], Form::Equal, true)
			->setRequired();

		$form->addSubmit("submit", "app.forms.favouriteProduct.labels.submit");
		$form->addSubmit("autoSubmit", "app.forms.favouriteProduct.labels.submit")
			->setValidationScope([])
			->onClick[] = function () {
			$this->redrawControl('filterList');
		};

		if (!$gridFilter) {
			$form->setDefaults($defaults);
		}
	}

	public function validateForm(\ADT\DoctrineForms\Form $form, array $inputs, ?GridFilter $gridFilter): void
	{
		$gridFilterQuery = $this->getGridFilterQuery()
			->byName($inputs['name'])
			->byGrid($this->grid->getName());

		if ($gridFilter) {
			$gridFilterQuery->byIdNot($gridFilter->getId());
		}

		if ($gridFilterQuery->count() > 0) {
			$form->addError(sprintf('Název %s se již používá', $inputs['name']));// TODO translate
		}

		if (!count($inputs['value'])) {
			$form->addError('Není vybraný žádný filter');// TODO translate
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
		} else {
			unset($filters[DataGrid::SELECTED_GRID_FILTER_KEY]);
			$filters = array_merge($filters, ['advancedSearch' => Json::encode($inputs['value'])]);
		}

		$grid = $this->grid->getGrid();
		$grid->setFilter($filters);
		$grid->handleRefreshState();
	}

	protected function parseListItems(array $list): array
	{
		$return = [];

		foreach ($list as $item) {
			$return[$item['id']] = $item['label'];
		}

		return $return;
	}

	protected function getTemplateFilename(): ?string
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
