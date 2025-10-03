<?php

namespace ADT\Datagrid\Component;

use ADT\Datagrid\Model\Queries\GridFilterQueryFactory;
use ADT\DoctrineComponents\EntityManager;
use ADT\QueryObjectDataSource\IQueryObjectDataSourceFactory;
use Nette\Localization\Translator;
use Nette\Security\User;

trait BaseGridDependencies
{
	abstract protected function getDataGridClass(): string;
	abstract protected function getTranslator(): Translator;
	abstract protected function getGridFilterQueryFactory(): GridFilterQueryFactory;
	abstract protected function getQueryObjectDataSourceFactory(): IQueryObjectDataSourceFactory;
	abstract protected function getSecurityUser(): User;
	abstract protected function getEntityManager(): EntityManager;
	abstract protected function getEmail(): string;
}