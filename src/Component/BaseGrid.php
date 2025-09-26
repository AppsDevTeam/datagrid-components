<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use ADT\Application\BasePresenter;
use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
use ADT\Datagrid\Model\Service\DatagridService;
use ADT\DoctrineComponents\EntityManager;
use ADT\DoctrineComponents\QueryObject\Filters\IsActiveFilter;
use ADT\DoctrineComponents\QueryObject\QueryObject;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use Closure;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Datagrid\Exception\DatagridException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Control;
use Nette\Application\UI\InvalidLinkException;
use Nette\Application\UI\Presenter;
use Nette\DI\Container;
use Nette\Localization\Translator;
use Nette\Security\User;
use ReflectionClass;
use ReflectionException;
use TypeError;

/**
 * @method BasePresenter getPresenter()
 */
abstract class BaseGrid extends Control
{
	use AutowireProperties;
	use AutowireComponentFactories;

	abstract protected function getDataGridClass(): string;
	abstract protected function getTranslator(): Translator;
	abstract protected function getGridFilterQueryFactory(): GridFilterQueryFactory;
	abstract protected function getQueryObjectDataSourceFactory(): IQueryObjectDataSourceFactory;
	abstract protected function getSecurityUser(): User;
	abstract protected function getEntityManager(): EntityManager;
	abstract protected function getEmail(): string;

	#[Autowire]
	protected DatagridService $datagridService;

	/** @var callable */
	protected $onDelete;
	protected bool $withoutIsActiveColumn = false;

	public function __construct()
	{
		$this->monitor(Presenter::class, function() {
			$grid = $this->getGrid();

			if (!method_exists($this, 'initGrid')) {
				throw new Exception('Define initGrid method!');
			}

			if (isset($grid->getParameter('filter')[DataGrid::SELECTED_GRID_FILTER_KEY])) {
				if (!$this->gridFilterQueryFactory->create()->byId($grid->getParameter('filter')[DataGrid::SELECTED_GRID_FILTER_KEY])->fetchOneOrNull()) {
					$grid->handleResetAdvancedFilter();
				}
			}

			$this->initGrid($grid);
			$this->addIsActive($grid);
		});
	}

	protected function createQueryObject(): QueryObject
	{
		return $this->getDic()->getByType($this->getQueryObjectFactoryClass())->create();
	}

	protected function getDic(): Container
	{
		return $this->autowirePropertiesLocator;
	}

	/**
	 * @throws DatagridException
	 * @throws Exception
	 */
	final protected function createComponentGrid(): DataGrid
	{
		/** @var DataGrid $grid */
		$grid = new ($this->getDataGridClass())();
		$grid->setTranslator($this->getTranslator());
		$grid->setEntityManager($this->getEntityManager());
		$grid->setDatagridService($this->datagridService);
		$grid->setGridFilterQueryFactory($this->getGridFilterQueryFactory());
		$grid->setOuterFilterRendering();
		$grid->setEmail($this->getEmail());
		$grid->setGridName($this->getGridName());

		$queryObject = $this->createQueryObject();
		$this->initQueryObject($queryObject);

		$queryObjectDataSource = $this->getQueryObjectDataSourceFactory()->create($queryObject);
		if ($this->getDataSourceFilterCallback()) {
			$queryObjectDataSource->setFilterCallback($this->getDataSourceFilterCallback());
		}
		$grid->setDataSource($queryObjectDataSource);

		if ($this->allowEdit()) {
			$grid->addAction('edit', '')
				->setIcon('edit')
				->setClass('ajax datagrid-edit');
		}

		if ($this->allowDelete() && $this->getSecurityUser()->isAllowed($this->allowDelete()->getAcl())) {
			$grid->addAction('delete', 'Smazat', 'delete!')
				->setIcon('trash')
				->setClass('ajax datagrid-delete')
				->setConfirmation(new StringConfirmation($this->getTranslator()->translate('action.delete.confirm')));
		}

		$grid->setSortableHandler($this->getName() . '-sortRows!');

		if ($grid->getTemplateFile() === $grid->getOriginalTemplateFile()) {
			$_reflectionClass = new ReflectionClass($this);
			$grid->setTemplateFile(dirname($_reflectionClass->getFileName()) . '/' . $_reflectionClass->getShortName() . '.latte');
		}

		return $grid;
	}

	public function getYesNoOptions(): array
	{
		return [
			'0' => 'no',
			'1' => 'yes'
		];
	}

	public function getGrid(): DataGrid
	{
		return $this['grid'];
	}

	protected function initQueryObject($queryObject): void
	{
		if ($queryObject instanceof IsActiveFilter) {
			$queryObject->disableIsActiveFilter();
		}
	}

	public function render(): void
	{
		$this->template->setFile(__DIR__ . '/BaseGrid.latte')->render();
	}

	protected function getDataSourceFilterCallback(): ?Closure
	{
		return null;
	}

	/**
	 * @throws AbortException
	 * @throws ReflectionException
	 * @throws NonUniqueResultException
	 * @throws NoResultException|InvalidLinkException
	 */
	final public function handleEdit(int $id): void
	{
		if (str_ends_with($this->allowEdit()->redirect, '!')) {
			$methodName = rtrim('handle' . ucfirst($this->allowEdit()->redirect), '!');

			try {
				$this->getPresenter()->{$methodName}($id);
			} catch (InvalidLinkException | TypeError) {
				$row = $this->createQueryObject()->byId($id)->getQuery()->getSingleResult();
				if (is_array($row)) {
					$entity = $row[0];
				} else {
					$entity = $row;
				}
				$this->getPresenter()->{$methodName}($entity);
			}
		} else {
			// because of "Argument $order passed to App\Modules\SystemModule\Orders\OrdersPresenter::actionEdit() must be App\Model\Entity\Order, integer given."
			// method Presenter::argsToParams doesn't respect router
			try {
				$this->getPresenter()->redirect($this->allowEdit()->redirect, $id);
			} catch (InvalidLinkException) {
				$this->getPresenter()->redirect($this->allowEdit()->redirect, $this->createQueryObject()->byId($id)->fetchOne());
			}
		}
	}

	/**
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function handleSortRows(): void
	{
		$itemId = $this->getParameter('item_id');
		$nextId = $this->getParameter('next_id');
		$previousId = $this->getParameter('prev_id');
		$item = $this->createQueryObject()->byId($itemId)->fetchOne();

		if ($previousId) {
			$previousItem = $this->createQueryObject()->byId($previousId)->fetchOne();
			$newPosition = $previousItem->getPosition() + 1;
		} else if ($nextId) {
			$nextItem = $this->createQueryObject()->byId($nextId)->fetchOne();
			$newPosition = $nextItem->getPosition() - 1;
		} else {
			$newPosition = 0;
		}

		if ($newPosition < 0) {
			$newPosition = 0;
		}

		$item->setPosition($newPosition);
		$this->getEntityManager()->flush();
	}

	/**
	 * @throws BadRequestException
	 * @throws ReflectionException
	 * @throws NonUniqueResultException
	 * @throws Exception
	 */
	final public function handleDelete($id): void
	{
		if (!$this->allowDelete()) {
			$this->getPresenter()->error();
		}

		if (!$this->getSecurityUser()->isAllowed($this->allowDelete()->acl)) {
			$this->getPresenter()->error();
		}

		$row = $this->createQueryObject()->byId($id)->getQuery()->getSingleResult();
		if (is_array($row)) {
			$entity = $row[0];
		} else {
			$entity = $row;
		}

		if ($this->allowDelete()->onDelete && !($this->allowDelete()->onDelete)($entity)) {
			return;
		}

		if ($this->getEntityManager()->isPossibleToDeleteEntity($entity)) {
			$this->getEntityManager()->remove($entity);
			$this->getEntityManager()->flush();
			$this->getPresenter()->flashMessageSuccess('action.delete.yes');
			$this->getGrid()->redrawControl();
		} else {
			$this->getPresenter()->flashMessageError('app.grids.flashes.cantDelete');
			$this->getPresenter()->redrawControl('flashes');
		}
	}

	final public function addFilterQuery(DataGrid $grid): void
	{
		$grid->addFilterText('q', '')
			->setTemplate('datagrid_filter_q.latte')
			->setCondition(function ($query, $value) {
				$query->byQuery($value);
			});
	}

	protected function allowEdit(): ?EditParams
	{
		return null;
	}

	protected function allowDelete(): ?DeleteParams
	{
		return null;
	}

	/**
	 * @throws DatagridException
	 */
	protected function addIsActive(DataGrid $grid): void
	{
		$class = $this->createQueryObject()->getEntityClass();
		$metadata = $this->getEntityManager()->getClassMetadata($class);

		if (
			$this->withoutIsActiveColumn
			|| isset($grid->getColumns()['isActive'])
			|| !$metadata->hasField('isActive')
		) {
			return;
		}

		$i = 1;
		$order = [];

		foreach ($grid->getColumns() as $key => $column) {
			if ($i === 2) {
				$order[] = 'isActive';
			}

			$order[] = $key;
			$i++;
		}
		if (count($order) === 1) {
			$order[] = 'isActive';
		}

		$grid->addColumnText('isActive', 'app.forms.global.isActive.label');
		$grid->setColumnsOrder($order);
		$grid->addIsActiveSwitcher();
	}

	/**
	 * @throws Exception
	 */
	public function handleDeleteGridFilter(): void
	{
		if (!$gridFilter = $this->getGridFilterQueryFactory()->create()->byId($this->getParameter('deleteId'))->fetchOneOrNull()) {
			$this->error();
		}

		$this->getEntityManager()->remove($gridFilter);
		$this->getEntityManager()->flush();
		$this->getPresenter()->flashMessageSuccess('action.delete.yes');

		$this->getGrid()->handleResetAdvancedFilter();
	}

	public function getGridName(): string
	{
		return $this->getPresenter()->getName() . '-' . $this->getName();
	}
}
