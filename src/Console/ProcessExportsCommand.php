<?php

namespace ADT\Datagrid\Console;

use ADT\Datagrid\Model\Entities\GridExport;
use ADT\Datagrid\Model\Service\DatagridService;
use ADT\DoctrineComponents\EntityManager;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'datagrid-components:process-exports', description: 'Process large exports.')]
class ProcessExportsCommand extends Command
{
	/**
	 * @throws Exception
	 */
	public function __construct(
		protected EntityManager $em,
		protected DatagridService $datagridService,
	)
	{
		parent::__construct();
	}

	/**
	 * @throws Exception
	 */
	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		foreach ($this->em->getRepository($this->em->findEntityClassByInterface(GridExport::class))->findBy(['inBackground' => 1, 'file' => null]) as $_gridExport) {
			$this->datagridService->processExport($_gridExport);
		}

		return \Symfony\Component\Console\Command\Command::SUCCESS;
	}
}
