<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use ADT\BackgroundQueue\BackgroundQueue;
use ADT\Datagrid\Model\Export\Excel\ExportExcel;
use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\DoctrineComponents\QueryObject\QueryObjectByMode;
use ADT\QueryObjectDataSource\QueryObjectDataSource;
use ADT\Datagrid\Model\Service\DataGridService;
use ADT\Utils\Utils;
use App\Model\Entities\GridFilter;
use App\Model\Queries\Base\BaseQuery;
use App\Model\Queries\Factories\GridFilterQueryFactory;
use App\UI\Portal\Components\Forms\Base\FormRenderer;
use App\UI\Portal\Components\Panels\GridFilterPanelControl\GridFilterPanelControlFactory;
use App\UI\Portal\Components\SidePanels\SidePanelControl;
use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Contributte\Datagrid\Column\ColumnDateTime;
use Contributte\Datagrid\Column\ColumnNumber;
use Contributte\Datagrid\Exception\DataGridException;
use Contributte\Datagrid\Export\ExportCsv;
use Contributte\Datagrid\Filter\Filter;
use Contributte\Datagrid\Filter\FilterMultiSelect;
use Contributte\Datagrid\Filter\FilterSelect;
use Contributte\Datagrid\Row;
use Contributte\Datagrid\Utils\ArraysHelper;
use Throwable;

class DataGrid extends \Contributte\Datagrid\Datagrid
{
	const string SELECTED_GRID_FILTER_SESSION_KEY = 'selectedGridFilter';
	const string TEMPORARY_GRID_FILTER_SESSION_KEY = 'temporaryGridFilter';

	public const TEMPLATE_DEFAULT = 'DataGrid.latte';
	public const TEMPLATE_PRETTY = 'DataGridPretty.latte';

	public const ACTION_NOT_DROPDOWN_ITEM = [
		'ajax datagrid-edit'
	];

	protected BackgroundQueue $backgroundQueue;

	protected GridFilterQueryFactory $gridFilterQueryFactory;

	public bool $strictSessionFilterValues = false;

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
		$gridClass = get_class($this->getParent());

		if ($this->getParameter(self::SELECTED_GRID_FILTER_SESSION_KEY)) {
			if (
				$selectedGridFilter = $this->gridFilterQueryFactory
					->create()
					->byId($this->getParameter(self::SELECTED_GRID_FILTER_SESSION_KEY))
					->fetchOneOrNull()
			) {
				$this->saveSessionData(self::SELECTED_GRID_FILTER_SESSION_KEY, ['id' => $selectedGridFilter->getId(), 'name' => $selectedGridFilter->getName()]);
				$this->setFilter(['advancedSearch' => Json::encode($selectedGridFilter->getValue())]);
				$this->deleteSessionData(self::TEMPORARY_GRID_FILTER_SESSION_KEY);
			}
		}

		if ($this->getSessionData(self::TEMPORARY_GRID_FILTER_SESSION_KEY)) {
			$this->deleteSessionData(self::SELECTED_GRID_FILTER_SESSION_KEY);
		}

		$this->template->actionsToDropdown = $this->actionsToDropdown;
		$this->template->translator = $this->translator;
		$this->template->gridClasses = $this->classes;
		$this->template->gridHtmlDataAttributes = $this->htmlDataAttributes;
		$this->template->showTableFoot = $this->showTableFoot;
		$this->template->toolbarButons = $this->toolbarButtons;
		$this->template->gridFilterColumns = Json::encode($this->getGridFilterFields());
		$this->template->gridClass = $gridClass;
		$this->template->selectedGridFilter = $this->getSessionData(self::SELECTED_GRID_FILTER_SESSION_KEY);
		$this->template->temporaryGridFilter = $this->getSessionData(self::TEMPORARY_GRID_FILTER_SESSION_KEY);
		$this->template->gridFilters = $this->gridFilterQueryFactory->create()->byGrid($gridClass)->fetch();

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

		if (!isset($this->exports[$id])) {
			throw new Nette\Application\ForbiddenRequestException;
		}

		if ($this->columnsExportOrder !== []) {
			$this->setColumnsOrder($this->columnsExportOrder);
		}

		$export = $this->exports[$id];

		/**
		 * Invoke possible events
		 */
		$this->onExport($this);

		if ($export->isFiltered()) {
			$sort = $this->sort;
			$filter = $this->assembleFilters();
		} else {
			$sort = [$this->primaryKey => 'ASC'];
			$filter = [];
		}

		if ($this->dataModel === null) {
			throw new DataGridException('You have to set a data source first.');
		}

		$rows = [];

		$items = $this->dataModel->filterData(
			null,
			$this->createSorting($sort, $this->sortCallback),
			$filter
		);

		foreach ($items as $item) {
			$rows[] = new Row($this, $item, $this->getPrimaryKey());
		}

		if ($export instanceof ExportCsv || $export instanceof ExportExcel) {
			$export->invoke($rows);
		} else {
			$export->invoke($items);
		}

		if ($export->isAjax()) {
			$this->reload();
		}
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

	public function addExportExcel(
		string $text,
		string $fileName,
		bool   $filtered = true
	): ExportExcel
	{
		$export = new ExportExcel($this, $text, $fileName, $filtered);
		$this->addToExports($export)->setIcon('file-export')->setText('');
		return $export;
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

	public function handleResetGridFilter(): void
	{
		$this->deleteSessionData(self::SELECTED_GRID_FILTER_SESSION_KEY);
		$this->deleteSessionData(self::TEMPORARY_GRID_FILTER_SESSION_KEY);
		$this->handleResetFilter();
		$this->redirect('this');
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

	public function getGridFilterFields(): array
	{
		$filters = $this->filters;
		unset ($filters['search']);
		unset ($filters['advancedSearch']);
		foreach ($filters as $_filter) {
			$fields[] = ['id' => $_filter->getKey()];
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
					} elseif ($column instanceof ColumnNumber) {
						$field['type'] = 'number';
					}
				}

				$field['label'] = $this->translator->translate($column->getName());
			}
		}

		return $fields;
	}

	public function addAdvancedFilteredSearch(): void
	{
		$fields = $this->getGridFilterFields();

		$this->addFilterText('advancedSearch', '', [])
			->setCondition(function (QueryObject $query, $value) {
				if ($value) {
					$advanceSearch = Json::decode($value, forceArrays: true);

					$seenValues = [];
					foreach ($advanceSearch as $key => $item) {
						$fieldValue = $item['value'];

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
							'eq' => QueryObjectByMode::EQUALS,
							'ne' => QueryObjectByMode::NOT_EQUALS,
							'sw' => QueryObjectByMode::STARTS_WITH,
							'ct' => QueryObjectByMode::CONTAINS,
							'nct' => QueryObjectByMode::NOT_CONTAINS,
							'fw' => QueryObjectByMode::ENDS_WITH,
							'in' => QueryObjectByMode::IN_ARRAY,
							'null' => QueryObjectByMode::IS_NULL,
							'nn' => QueryObjectByMode::IS_NOT_NULL,
							'gt' => QueryObjectByMode::GREATER,
							'lt' => QueryObjectByMode::LESS,
							'bw' => QueryObjectByMode::BETWEEN,
							'nbw' => QueryObjectByMode::NOT_BETWEEN,
						];

						if (!empty($searchFilter['value2'])) {
							$value = [
								Utils::getDateTimeFromArray($searchFilter['value']) ?: $searchFilter['value'],
								Utils::getDateTimeFromArray($searchFilter['value2']) ?: $searchFilter['value2'],
							];
						} else {
							$value = Utils::getDateTimeFromArray($searchFilter['value']) ?: $searchFilter['value'];
							if ($operatorMap[$searchFilter['operator']] === QueryObjectByMode::IN_ARRAY && !is_array($value)) {
								$delimiter = $searchFilter['delimiter'] ?? ',';
								$value = explode($delimiter, $value);
							}
						}

						if (
							Utils::realEmpty($value)
							&& !in_array($operatorMap[$searchFilter['operator']], [QueryObjectByMode::IS_NULL, QueryObjectByMode::IS_NOT_NULL])
						) {
							continue;
						}

						$label = $searchFilter['label'];

						// without this line, I will get Typed property Contributte\Datagrid\Filter\Filter::$value must not be accessed before initialization
						$this->getFilter($label)->setValue($value);
						$column = array_keys($this->getFilter($label)->getCondition());

						$query->by(
							(!empty($column) ? $column : $label),
							$value,
							$operatorMap[$searchFilter['operator']] ?? QueryObjectByMode::EQUALS
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

	public function setGridFilterQuery(GridFilterQueryFactory $queryFactory): self
	{
		$this->gridFilterQueryFactory = $queryFactory;
		return $this;
	}

	public function handleEditGridFilter(): void
	{
		$this->redrawSidePanel('gridFilter');
	}

	public function createComponentGridFilterSidePanel(GridFilterPanelControlFactory $factory): SidePanelControl
	{
		if ($this->getParameter('columns')) {
			$this->gridFilterParameters = Json::decode($this->getParameter('columns'), forceArrays: true);
		}
		if ($this->getParameter('gridFilterClass')) {
			$this->gridFilterClass = $this->getParameter('gridFilterClass');
		}

		$gridFilter = $this->getParameter('editId')
			? $this->gridFilterQueryFactory->create()->byId($this->getParameter('editId'))->fetchOneOrNull()
			: (new GridFilter())
				->setGrid($this->gridFilterClass)
				->setCompany($this->securityUser->getIdentity()->getFilteredCompany());

		$form = $this->gridFilterFormFactory->create()
			->setEntity($gridFilter)
			->setFilterList($this->gridFilterParameters);

		return $factory->create()
			->setEntity($gridFilter)
			->setForm($form);
	}
}
