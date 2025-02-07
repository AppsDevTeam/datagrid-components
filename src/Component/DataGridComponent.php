<?php

namespace App\Component;

use App\Model\Entities\GridFilter;
use App\UI\Portal\Components\Panels\GridFilterPanelControl\GridFilterPanelControlFactory;
use App\UI\Portal\Components\SidePanels\SidePanelControl;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\UI\Control;
use Nette\ComponentModel\IComponent;
use Nette\Utils\Json;

class DataGridComponent extends Control implements IComponent
{
	protected EntityManagerInterface $em;

	public function setEntityManager(EntityManagerInterface $em): void
	{
		$this->em = $em;
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->em;
	}

	public function handleEditGridFilter(): void
	{
		$this->redrawSidePanel('gridFilter');
	}

	public function handleSortRows(): void
	{
		$itemId = $this->getParent()->getParameter('item_id');
		$nextId = $this->getParent()->getParameter('next_id');
		$previousId = $this->getParent()->getParameter('prev_id');
		$item = $this->getParent()->getQueryObject()->byId($itemId)->fetchOne();

		if ($previousId) {
			$previousItem = $this->getParent()->getQueryObject()->byId($previousId)->fetchOne();
			$newPosition = $previousItem->getPosition() + 1;
		} else if ($nextId) {
			$nextItem = $this->getParent()->getQueryObject()->byId($nextId)->fetchOne();
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

	public function render(): void
	{
		$this->template->gridFilters = $this->gridFilterQueryFactory->create()->byGrid($gridClass)->fetch();

		parent::render();
	}
}