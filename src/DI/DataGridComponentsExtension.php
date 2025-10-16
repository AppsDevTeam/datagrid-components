<?php

namespace ADT\Datagrid\DI;

use ADT\Datagrid\Console\ProcessExportsCommand;
use ADT\Datagrid\Model\Service\DatagridService;
use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\DI\CompilerExtension;

class DataGridComponentsExtension extends CompilerExtension implements TranslationProviderInterface
{
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('datagridService'))
			->setType(DatagridService::class)
			->setArgument('config', $this->getConfig());

		$def = $builder->addDefinition($this->prefix('processExportsCommand'))
			->setType(ProcessExportsCommand::class);
		$def->addSetup('setLocksDir', [$this->config['locksDir']]);
	}

	public function getTranslationResources(): array
	{
		return [__DIR__ . '/../lang'];
	}
}