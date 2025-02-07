<?php

declare(strict_types=1);

namespace ADT\Datagrid\Component;

use ADT\BackgroundQueue\BackgroundQueue;
use ADT\DoctrineComponents\QueryObject;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use App\Model\Query\QueryObjectFactory;
use ADT\Datagrid\Model\Security\ISecurityUser;
use ADT\Datagrid\Model\Service\DeleteService;
use Nette\Application\UI\Presenter;
use Closure;
use Contributte\Translation\Translator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Kdyby\Autowired\Attributes\Autowire;
use Kdyby\Autowired\AutowireComponentFactories;
use Kdyby\Autowired\AutowireProperties;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Control;
use Nette\Application\UI\InvalidLinkException;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\DI\Container;
use ReflectionException;
use Ublaboo\DataGrid\Column\Action\Confirmation\StringConfirmation;
use Ublaboo\DataGrid\Exception\DataGridException;

/**
 * @property-read DataGrid $grid
 * @property-read Presenter $presenter
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
	protected DeleteService $deleteService;

	#[Autowire]
	protected BackgroundQueue $backgroundQueue;

	protected ISecurityUser $securityUser;

	/** @var callable */
	protected $onDelete;

	protected ?string $linkDetail = null;

	protected static string $templateFile = DataGrid::TEMPLATE_DEFAULT;
	protected string $project;

	abstract protected function getQueryObjectFactoryClass(): string;
	abstract protected function initGrid(DataGrid $grid): void;

	final protected function createComponentGrid(): DataGrid
	{
		$grid = $this->createGridInstance();
		$grid->setTranslator($this->translator);
		$grid->setBackgroundQueue($this->backgroundQueue);
		$this->securityUser = $this->getPresenter()->getUser();

		$grid->setOuterFilterRendering();

		$queryObject = $this->createQueryObject();
		$this->initDataSource($queryObject);

		$queryObjectDataSource = $this->queryObjectDataSource->create($queryObject);
		if ($this->getDataSourceFilterCallback()) {
			$queryObjectDataSource->setFilterCallback($this->getDataSourceFilterCallback());
		}
		$grid->setDataSource($queryObjectDataSource);

		$this->initGrid($grid);

		if ($grid->isSortable()) {
			$grid->setSortableHandler('sortRows!');
		}

		if ($grid->getTemplateFile() === $grid->getOriginalTemplateFile()) {
			$_reflectionClass = new \ReflectionClass($this);
			$grid->setTemplateFile(dirname($_reflectionClass->getFileName()) . '/' . $_reflectionClass->getShortName() . '.latte');
		}

		return $grid;
	}

	protected function createGridInstance(): DataGrid
	{
		return new DataGrid(static::$templateFile);
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
		/** @var QueryObjectFactory $queryObjectFactory */
		$queryObjectFactory = $this->getDic()->getByType($this->getQueryObjectFactoryClass());
		return $queryObjectFactory->create();
	}

	private function createBaseEntityQueryObject(): QueryObject
	{
		if (method_exists($this, 'getBaseEntityQueryFactoryClass')) {
			$queryObjectFactory = $this->getDic()->getByType($this->getBaseEntityQueryFactoryClass());
			return $queryObjectFactory->create();
		}
		return $this->createQueryObject();
	}

	/**
	 * @throws DataGridException
	 */
	final public function render(): void
	{
		/** @var Template $template */
		$template = $this->grid->getTemplate();

		$this->renderGrid($template);

		if ($this->linkDetail) {
			$this->grid->addAction('detail', '', 'detail!')
				->setIcon('magnifying-glass')
				->setClass('ajax datagrid-detail');
		}

		if ($this->allowEdit() && $this->securityUser->isAllowed($this->allowEdit()->getAcl())) {
			$this->grid->addAction('edit', '', 'edit!')
				->setIcon('edit')
				->setClass('ajax datagrid-edit');
			if ($this->allowEdit()->getCondition()) {
				$this->grid->getAction('edit')->setRenderCondition($this->allowEdit()->getCondition());
			}
		}

		if ($this->allowDelete() && $this->securityUser->isAllowed($this->allowDelete()->getAcl())) {
			$this->grid->addAction('delete', '', 'delete!')
				->setIcon('trash-can')
				->setClass('ajax datagrid-delete')
				->setConfirmation(new StringConfirmation('action.delete.confirm'));
			if ($this->allowDelete()->getCondition()) {
				$this->grid->getAction('delete')->setRenderCondition($this->allowDelete()->getCondition());
			}
		}

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
	 * @throws NoResultException
	 */
	final public function handleEdit(int $id): void
	{
		if (str_ends_with($this->allowEdit()->redirect, '!')) {
			$methodName = rtrim('handle' . ucfirst($this->allowEdit()->redirect), '!');

			try {
				$this->getPresenter()->{$methodName}($id);
			} catch (InvalidLinkException|\TypeError) {
				$this->getPresenter()->{$methodName}($this->createBaseEntityQueryObject()->byId($id)->fetchOne());
			}

		} else {
			// because of "Argument $order passed to App\Modules\SystemModule\Orders\OrdersPresenter::actionEdit() must be App\Model\Entity\Order, integer given."
			// method Presenter::argsToParams doesn't respect router
			try {
				$this->presenter->redirect($this->allowEdit()->redirect, $id);
			} catch (InvalidLinkException) {
				$this->presenter->redirect($this->allowEdit()->redirect, $this->createBaseEntityQueryObject()->byId($id)->fetchOne());
			}
		}
	}

	public function setLinkDetail(?string $linkDetail): static
	{
		$this->linkDetail = $linkDetail;
		return $this;
	}

	public function handleDetail(int $id): void
	{
		$this->redirectHandle($this->linkDetail, $id);
	}

	public function redirectHandle(string $link, int $id)
	{
		try {
			$this->presenter->redirect($link, $id);
		} catch (InvalidLinkException) {
			$this->presenter->redirect($link, $this->createBaseEntityQueryObject()->byId($id)->fetchOne());
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

		if (!$entity = $this->createBaseEntityQueryObject()->byId($id)->fetchOneOrNull()) {
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
			$this->presenter->flashMessageError('Not possible to delete the entity because it is used by other entities.');
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

	protected function initDataSource($queryObject): void
	{
	}

	protected function renderGrid(Template $template): void
	{
	}

	protected function getDic(): Container
	{
		return $this->autowirePropertiesLocator;
	}


	public function translateArray(array $array): array
	{
		return array_map(fn(string $value) => $this->translator->translate($value), $array);
	}

	public function setProject(string $project): void
	{
		$this->project = $project;
	}

	public function setBackgroundQueue(BackgroundQueue $backgroundQueue): void
	{
		$this->backgroundQueue = $backgroundQueue;
	}
}
