<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use ADT\BackgroundQueue\BackgroundQueue;
use ADT\DoctrineComponents\QueryObject;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use App\Model\Doctrine\EntityManager;
use App\Model\Filters;
use App\Model\Queries\Factories\GridFilterQueryFactory;
use App\Model\Queries\Filters\IsActiveInterface;
use App\Model\Security\SecurityUser;
use App\Model\Services\DeleteService;
use App\UI\BasePresenter;
use Closure;
use Contributte\Translation\Exceptions\InvalidArgument;
use Contributte\Translation\Translator;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Control;
use Nette\Application\UI\InvalidLinkException;
use Nette\DI\Container;
use ReflectionClass;
use ReflectionException;
use Ublaboo\DataGrid\Column\Action\Confirmation\StringConfirmation;
use Ublaboo\DataGrid\Exception\DataGridException;

/**
 * @property-read DataGrid $grid
 * @property-read BasePresenter $presenter
 */
abstract class BaseGrid extends Control
{
	use AutowireProperties;
	use AutowireComponentFactories;

	#[Autowire]
	protected Translator $translator;

	#[Autowire]
	protected IQueryObjectDataSourceFactory $queryObjectDataSource;

	#[Autowire]
	protected SecurityUser $securityUser;

	#[Autowire]
	protected EntityManager $em;

	#[Autowire]
	protected DeleteService $deleteService;

	#[Autowire]
	protected GridFilterQueryFactory $gridFilterQueryFactory;

	#[Autowire]
	protected Filters $filters;

	#[Autowire]
	protected BackgroundQueue $backgroundQueue;

	/** @var callable */
	protected $onDelete;
	protected static string $templateFile = DataGrid::TEMPLATE_DEFAULT;
	protected bool $withoutIsActiveColumn = false;

	abstract protected function initGrid(DataGrid $grid): void;

	/**
	 * @throws InvalidArgument
	 * @throws DataGridException
	 */
	final protected function createComponentGrid(): DataGrid
	{
		$grid = new DataGrid(static::$templateFile);
		$grid->setTranslator($this->translator);
		$grid->setGridFilterQuery($this->gridFilterQueryFactory);
		$grid->setBackgroundQueue($this->backgroundQueue);
		$this->securityUser = $this->getPresenter()->getUser();

		$grid->setOuterFilterRendering();

		$queryObject = $this->createQueryObject();

		$this->initQueryObject($queryObject);

//		$grid->onFiltersAssembled[] = function ($filters) {
//			foreach ($filters as $key => $filter) {
//				if ($filter->getValue()) {
//					$filter->addAttribute('class', 'sent');
//				}
//			}
//
//			$this['grid']->redrawControl('outer-filters');
//		};

		$queryObjectDataSource = $this->queryObjectDataSource->create($queryObject);

		if ($this->getDataSourceFilterCallback()) {
			$queryObjectDataSource->setFilterCallback($this->getDataSourceFilterCallback());
		}
		$grid->setDataSource($queryObjectDataSource);

		if ($this->allowEdit()) {
			$grid->addAction('edit', '')
				->setIcon('edit')
				->setClass('ajax datagrid-edit');
		}

		if ($this->allowDelete() && $this->securityUser->isAllowed($this->allowDelete()->getAcl())) {
			$grid->addAction('delete', 'Smazat', 'delete!')
				->setIcon('trash')
				->setClass('ajax datagrid-delete')
				->setConfirmation(new StringConfirmation($this->translator->translate('action.delete.confirm')));
		}
		$this->initGrid($grid);
		$this->addIsActive($grid);

		if ($grid->isSortable()) {
			$grid->setSortableHandler('sortRows!');
		}

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

	protected function createQueryObject(): QueryObject
	{
		return $this->getDic()->getByType($this->getQueryObjectFactoryClass())->create();
	}

	protected function initQueryObject($queryObject): void
	{
		if ($queryObject instanceof IsActiveInterface) {
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
			} catch (InvalidLinkException | \TypeError) {
				$this->getPresenter()->{$methodName}($this->createQueryObject()->byId($id)->fetchOne());
			}
		} else {
			// because of "Argument $order passed to App\Modules\SystemModule\Orders\OrdersPresenter::actionEdit() must be App\Model\Entity\Order, integer given."
			// method Presenter::argsToParams doesn't respect router
			try {
				$this->presenter->redirect($this->allowEdit()->redirect, $id);
			} catch (InvalidLinkException) {
				$this->presenter->redirect($this->allowEdit()->redirect, $this->createQueryObject()->byId($id)->fetchOne());
			}
		}
	}

	/**
	 * @throws BadRequestException
	 * @throws ReflectionException
	 * @throws NonUniqueResultException
	 */
	final public function handleDelete($id): void
	{
		if (!$this->allowDelete()) {
			$this->error();
		}

		if (!$this->securityUser->isAllowed($this->allowDelete()->acl)) {
			$this->error();
		}

		if (!$entity = $this->createQueryObject()->byId($id)->fetchOneOrNull()) {
			$this->error();
		}

		if ($this->allowDelete()->onDelete && !($this->allowDelete()->onDelete)($entity)) {
			return;
		}

		if ($this->deleteService->isPossibleToDeleteEntity($entity)) {
			$this->deleteService->delete($entity);
			$this->presenter->flashMessageSuccess('action.delete.yes');
			$this->grid->redrawControl();
		} else {
			$this->presenter->flashMessageError('app.grids.flashes.cantDelete');
			$this->presenter->redrawControl('flashes');
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

	protected function getDic(): Container
	{
		return $this->autowirePropertiesLocator;
	}

	protected function getEntityManager(): EntityManager
	{
		return $this->em;
	}

	protected function getDataSource(): QueryObject
	{
		if ($this->queryObject) {
			return $this->queryObject;
		}

		return $this->createQueryObject();
	}

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

		$grid->addColumnText('isActive', 'app.forms.global.isActive');
		$grid->setColumnsOrder($order);
	}
}
