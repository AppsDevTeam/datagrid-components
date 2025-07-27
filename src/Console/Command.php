<?php

namespace ADT\Datagrid\Console;

use ADT\CommandLock\CommandLock;
use ADT\CommandLock\Storage\FileSystemStorage;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends \Symfony\Component\Console\Command\Command
{
	use CommandLock;

	private string $locksDir;

	abstract protected function executeCommand(InputInterface $input, OutputInterface $output): int;

	public function setLocksDir(string $locksDir): static
	{
		$this->locksDir = $locksDir;
		return $this;
	}

	/**
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->setStorage(new FileSystemStorage($this->locksDir));

		$this->lock();

		$status = $this->executeCommand($input, $output);

		$this->unlock();

		return $status;
	}
}
