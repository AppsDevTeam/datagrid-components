<?php

namespace ADT\Datagrid\DI;

use ADT\Datagrid\Model\Service\DataGridService;
use Nette\DI\CompilerExtension;

class DataGridComponentsExtension extends CompilerExtension
{
	const string DATA_GRID_SERVICE_PREFIX = 'dataGridService';

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix(self::DATA_GRID_SERVICE_PREFIX))
			->setType(DataGridService::class);
	}
}