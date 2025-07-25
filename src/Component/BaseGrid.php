<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use ADT\Application\BasePresenter;
use ADT\BackgroundQueue\BackgroundQueue;
use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
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
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Control;
use Nette\Application\UI\InvalidLinkException;
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
	abstract protected function getDataGridClass(): string;
	abstract protected function getTranslator(): Translator;
	abstract protected function getGridFilterQueryFactory(): GridFilterQueryFactory;
	abstract protected function getQueryObjectDataSourceFactory(): IQueryObjectDataSourceFactory;
	abstract protected function getSecurityUser(): User;
	abstract protected function createQueryObject(): QueryObject;
	abstract protected function getEntityManager(): EntityManager;
	abstract protected function getBackgroundQueue(): BackgroundQueue;

	/** @var callable */
	protected $onDelete;
	protected static string $templateFile = DataGrid::TEMPLATE_DEFAULT;
	protected bool $withoutIsActiveColumn = false;

	/**
	 * @throws DatagridException
	 */
	final protected function createComponentGrid(): DataGrid
	{
		/** @var DataGrid $grid */
		$grid = new ($this->getDataGridClass())(static::$templateFile);
		$grid->setTranslator($this->getTranslator());
		$grid->setGridFilterQueryFactory($this->getGridFilterQueryFactory());
		$grid->setOuterFilterRendering();
		$grid->setBackgroundQueue($this->getBackgroundQueue());

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
		$grid->setGridName($this->getPresenter()->getName() . '-' . $this->getName());
		$this->initGrid($grid);
		$this->addIsActive($grid);

		if ($grid->isSortable()) {
			$grid->setSortableHandler($this->name . '-sortRows!');
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
				$this->getPresenter()->{$methodName}($this->createQueryObject()->byId($id)->fetchOne());
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

		if (!$entity = $this->createQueryObject()->byId($id)->fetchOneOrNull()) {
			$this->getPresenter()->error();
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

		$grid->addColumnText('isActive', 'app.forms.global.isActive');
		$grid->setColumnsOrder($order);
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
}
