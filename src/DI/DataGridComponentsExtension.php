<?php

namespace ADT\Datagrid\DI;

use ADT\Datagrid\Model\Service\DataGridService;
use ADT\Datagrid\Model\Service\DeleteService;
use Nette\DI\CompilerExtension;

class DataGridComponentsExtension extends CompilerExtension
{
	const DELETE_SERVICE_PREFIX = 'deleteService';
	const DATA_GRID_SERVICE_PREFIX = 'dataGridService';

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix(self::DELETE_SERVICE_PREFIX))
			->setType(DeleteService::class);

		$builder->addDefinition($this->prefix(self::DATA_GRID_SERVICE_PREFIX))
			->setType(DataGridService::class);
	}
}