<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use ADT\BackgroundQueue\BackgroundQueue;
use ADT\DoctrineComponents\QueryObjectByMode;
use ADT\DoctrineComponents\QueryObjectInterface;
use ADT\QueryObjectDataSource\QueryObjectDataSource;
use App\Components\Forms\Base\FormRenderer;
use ADT\Datagrid\Model\Service\DataGridService;
use ADT\Datagrid\Model\Utils;
use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Ublaboo\DataGrid\Column\ColumnDateTime;
use Ublaboo\DataGrid\Column\ColumnNumber;
use Ublaboo\DataGrid\Exception\DataGridException;
use Ublaboo\DataGrid\Export\ExportCsv;
use Ublaboo\DataGrid\Filter\Filter;
use Ublaboo\DataGrid\Filter\FilterMultiSelect;
use Ublaboo\DataGrid\Filter\FilterSelect;
use Ublaboo\DataGrid\Utils\ArraysHelper;

class DataGrid extends \Ublaboo\DataGrid\DataGrid
{
	public const TEMPLATE_DEFAULT = 'DataGrid.latte';
	public const TEMPLATE_PRETTY = 'DataGridPretty.latte';

	public const ACTION_DROPDOWN_ITEM = 'dropdown-item';

	protected BackgroundQueue $backgroundQueue;

	public $strictSessionFilterValues = false;

	/** @var string */
	protected string $templateType;

	protected array $classes = [];
	protected array $htmlDataAttributes = [];

	protected bool $actionsToDropdown = true;

	protected bool $showTableFoot = true;

	public function isActionsToDropdown(): bool
	{
		return $this->actionsToDropdown;
	}

	public function setActionsToDropdown($actionsToDropdown): static
	{
		$this->actionsToDropdown = $actionsToDropdown;
		return $this;
	}

	public function __construct(
		string $templateType = self::TEMPLATE_DEFAULT,
		?Nette\ComponentModel\IContainer $parent = null,
		?string $name = null,
	)
	{
		$this->templateType = $templateType;
		parent::__construct($parent, $name);
	}

	public function getOriginalTemplateFile(): string
	{
		return __DIR__ . '/' . $this->templateType;
	}

	/**
	 * Get associative array of items_per_page_list
	 * @return array
	 */
	public function getItemsPerPageList(): array
	{
		$this->setItemsPerPageList([20, 50], false);
		return parent::getItemsPerPageList();
	}

	public function createComponentFilter(): Form
	{
		$form = parent::createComponentFilter();
		$form->setRenderer(new FormRenderer($form));
		return $form;
	}

	public function createComponentPaginator(): DataGridPaginator
	{
		$component = new DataGridPaginator(
			$this->getTranslator(),
			static::$iconPrefix,
		);
		$paginator = $component->getPaginator();

		$paginator->setPage($this->page);
		$paginator->setItemsPerPage($this->getPerPage());

		return $component;
	}

	public function render(): void
	{
		$this->template->actionsToDropdown = $this->actionsToDropdown;
		$this->template->translator = $this->translator;
		$this->template->gridClasses = $this->classes;
		$this->template->gridHtmlDataAttributes = $this->htmlDataAttributes;
		$this->template->showTableFoot = $this->showTableFoot;
		$this->template->toolbarButons = $this->toolbarButtons;

		parent::render();
	}

	/**
	 * @throws DataGridException
	 */
	public function handleExport($id): void
	{
		$dataSource = $this->dataModel->getDataSource();
		if ($dataSource instanceof QueryObjectDataSource) {
			$this->dataModel->onAfterFilter[] = function() use ($dataSource, $id) {
				if ($dataSource->getCount() > 10000) {
					$columns = [];
					$ids = $dataSource->getQueryObject()->fetchField('id');

					foreach ($this->columns as $key => $column) {
						$columns[$key] = [
							'name' => $column->getName(),
							'column' => $column->getColumn(),
						];
					}

					$this->backgroundQueue->publish('dataGridExport', [
						'ids' => array_values($ids),
						'columns' => $columns,
						'entityClass' => $dataSource->getQueryObject()->getEntityClass(),
						'userMail' => $this->getPresenter()->getUser()->getIdentity()->getEmail(),
						'downloadLink' => $this->getPresenter()->link('//:Admin:Download:file', DataGridService::FILE_ID_VARIABLE),
					]);

					$this->getPresenter()->flashMessageInfo('Export will be processed in background and sent to your email when finished.');
					$this->redirect('this');
				}
			};
		}

		parent::handleExport($id);
	}

	/**
	 * Pridáva gridu class
	 */
	public function addClasses(array|string $classes): void
	{
		if (!is_array($classes)) {
			$this->classes[] = $classes;
		} else {
			$this->classes = array_merge($this->classes, $classes);
		}
	}

	/**
	 * Pridáva gridu data-attributy
	 */
	public function addHtmlDataAttribute(array|string $attr): void
	{
		if (!is_array($attr)) {
			$this->htmlDataAttributes[] = $attr;
		} else {
			$this->htmlDataAttributes = array_merge($this->htmlDataAttributes, $attr);
		}
	}

	public function showTableFoot(bool $bool): static
	{
		$this->showTableFoot = $bool;
		return $this;
	}

	public function addFilterMultiSelect(
		string $key,
		string $name,
		array $options,
		?string $column = null,
	): FilterMultiSelect {
		return parent::addFilterMultiSelect($key, $name, $options, $column)
			->setAttribute('class', []);
	}

	public function addColumnDateTime(string $key, string $name, ?string $column = null): ColumnDateTime
	{
		return parent::addColumnDateTime($key, $name, $column)->setAlign('left');
	}

	public function addColumnNumber(string $key, string $name, ?string $column = null): ColumnNumber
	{
		return parent::addColumnNumber($key, $name, $column)->setAlign('left');
	}

	public function addFilterSelectMonth(string $key, string $name): FilterSelect|Filter
	{
		return $this->addFilterSelect($key, $name, [
			1 => $this->getTranslator()->translate('common.january'),
			2 => $this->getTranslator()->translate('common.february'),
			3 => $this->getTranslator()->translate('common.march'),
			4 => $this->getTranslator()->translate('common.april'),
			5 => $this->getTranslator()->translate('common.may'),
			6 => $this->getTranslator()->translate('common.june'),
			7 => $this->getTranslator()->translate('common.july'),
			8 => $this->getTranslator()->translate('common.august'),
			9 => $this->getTranslator()->translate('common.september'),
			10 => $this->getTranslator()->translate('common.october'),
			11 => $this->getTranslator()->translate('common.november'),
			12 => $this->getTranslator()->translate('common.december'),
		])->setCondition(function () {});
	}

	public function addFilterSelectYear(string $key, string $name, ?string $maxYear = null): FilterSelect|Filter
	{
		if ($maxYear === null) {
			$maxYear = (new DateTime())->format('Y');
		}

		$yearsRange = range($maxYear, 2022);

		return $this->addFilterSelect($key, $name, array_combine($yearsRange, $yearsRange))
			->setCondition(function () {
			});
	}

	public function addFilterSelectQuarter(string $key, string $name): FilterSelect|Filter
	{
		return $this->addFilterSelect($key, $name, [
			1 => 'I.',
			2 => 'II.',
			3 => 'III.',
			4 => 'IV.',
			'*' => 'I.-IV.'
		])->setCondition(function () {});
	}

	public function addExportCsv(
		string $text,
		string $csvFileName,
		string $outputEncoding = 'utf-8',
		string $delimiter = ';',
		bool $includeBom = false,
		bool $filtered = true
	): ExportCsv
	{
		return parent::addExportCsv('', $csvFileName, $outputEncoding, $delimiter, $includeBom, $filtered)
			->setIcon('file-export');
	}


	public function isFilterActive(): bool
	{
		$filters = $this->filter;
		if (isset($filters['search'])) {
			$filters['search'] = null;
		}

		if (isset($filters['advancedSearch']) && $filters['advancedSearch'] === '[]') {
			$filters['advancedSearch'] = null;
		}

		$is_filter = ArraysHelper::testTruthy($filters);

		return $is_filter || $this->forceFilterActive;
	}


	public function handleResetFilter(): void
	{
		$searchFilter = $this->filter['search'] ?? null;
		$advancedSearchFilter = $this->filter['advancedSearch'] ?? null;
		parent::handleResetFilter();

		if ($searchFilter) {
			$this->filter['search'] = $searchFilter;
			$this->filter['advancedSearch'] = $advancedSearchFilter;
		}
	}

	/**
	 * Add filter for ajax entity select
	 * @param string        $key
	 * @param string        $name
	 * @param string        $entityName
	 * @param string        $prompt
	 * @param array|string  $columns
	 * @param callable|NULL $onAddToForm Callback called when component is added to the container with component as argument
	 * @return \App\Components\Grids\Base\FilterAjaxSelect
	 * @throws DataGridException
	 */
	public function addFilterAjaxSelect($key, $name, $entityName, $prompt, $columns = NULL, $onAddToForm = NULL)
	{
		$columns = NULL === $columns ? [$key] : (is_string($columns) ? [$columns] : $columns);

		if (!is_array($columns)) {
			throw new DataGridException("Filter AjaxSelect can accept only array or string.");
		}

		$this->addFilterCheck($key);

		$filterAjaxSelect = new FilterAjaxSelect($this, $key, $name, $entityName, $prompt, $columns, $onAddToForm);
		$filterAjaxSelect->setAttribute('data-adt-select2', true);
		return $this->filters[$key] = $filterAjaxSelect;
	}


	public function addAdvancedFilteredSearch(array $fields = [], bool $includeAllColumns = true): void
	{
		if ($includeAllColumns) {
			$fields = [
				...array_map(fn(string $key) => ['id' => $key], array_keys($this->columns)),
				...$fields
			];
		}

		foreach ($fields as &$field) {
			$id = $field['id'];
			$column = $this->columns[$id];

			if (!empty($column)) {
				if (empty($field['type'])) {
					$field['type'] = 'text';

					if (!empty($this->filters[$id]) && $this->filters[$id] instanceof FilterSelect) {
						$field['type'] = 'list';
						$options = $this->filters[$id]->getOptions();
						$field['list'] = array_map(function ($value, $key) {
							return ['id' => $key, 'label' => $value];
						}, $options, array_keys($options));
					}

					if ($column instanceof ColumnDateTime) {
						$field['type'] = 'date';
					}
				}

				$field['label'] = $column->getName();
			}
		}

		$this->addFilterText('advancedSearch', '', [])
			->setCondition(function (QueryObjectInterface $query, $value) {

				if ($value) {
					$advanceSearch = Json::decode($value, Json::FORCE_ARRAY);

					$seenValues = [];
					foreach ($advanceSearch as $key => $item) {
						$fieldValue = $item['field']['value'];

						if (in_array($fieldValue, $seenValues)) {
							// Odstraníme duplicity
							unset($advanceSearch[$key]);
						} else {
							$seenValues[] = $fieldValue;
						}
					}
					$advanceSearch = array_values($advanceSearch);

					foreach ($advanceSearch as $searchFilter) {
						$operatorMap = [
							'eq' => QueryObjectByMode::STRICT,
							'ne' => QueryObjectByMode::NOT_EQUAL,
							'sw' => QueryObjectByMode::STARTS_WITH,
							'ct' => QueryObjectByMode::CONTAINS,
							'nct' => QueryObjectByMode::NOT_CONTAINS,
							'fw' => QueryObjectByMode::ENDS_WITH,
							'in' => QueryObjectByMode::IN_ARRAY,
							'null' => QueryObjectByMode::IS_EMPTY,
							'nn' => QueryObjectByMode::IS_NOT_EMPTY,
							'gt' => QueryObjectByMode::GREATER_OR_EQUAL,
							'lt' => QueryObjectByMode::LESS_OR_EQUAL,
							'bw' => QueryObjectByMode::BETWEEN,
							'nbw' => QueryObjectByMode::NOT_BETWEEN,
						];

						if (!empty($searchFilter['value']['value2'])) {
							$value = [
								$searchFilter['value']['value'],
								$searchFilter['value']['value2']
							];

							$value = array_map(function ($_value) {
								if ($date = \DateTimeImmutable::createFromFormat('m/d/Y', $_value)) {
									$_value = $date;
								}
								return $_value;
							}, $value);
						} else {
							$value = $searchFilter['value']['value'];
							if ($date = \DateTimeImmutable::createFromFormat('m/d/Y', $value)) {
								$value = $date;
							} elseif ($operatorMap[$searchFilter['operator']['value']] === QueryObjectByMode::IN_ARRAY) {
								$delimiter = $searchFilter['value']['delimiter'] ?? ',';
								$value = explode($delimiter, $value);
							}
						}

						if (Utils::realEmpty($value)
							&& !in_array($operatorMap[$searchFilter['operator']['value']], [QueryObjectByMode::IS_EMPTY, QueryObjectByMode::IS_NOT_EMPTY])
						) {
							continue;
						}

						$label = $searchFilter['field']['value'];

						try {
							$column = array_keys($this->getFilter($label)->getCondition());
						} catch (\Exception) {
							$column = $this->getColumn($label)->getColumn();
						}

						$query->by(
							(!empty($column) ? $column : $label),
							$value,
							$operatorMap[$searchFilter['operator']['value']] ?? QueryObjectByMode::STRICT
						);
					}
				}
			})
			->setAttribute('data-filter-fields', Json::encode($fields));
	}

	public function setBackgroundQueue(BackgroundQueue $backgroundQueue): self
	{
		$this->backgroundQueue = $backgroundQueue;
		return $this;
	}
}
