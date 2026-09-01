<?php

namespace ADT\Datagrid\DI;

use ADT\Datagrid\Model\Export\CsvExportGenerator;
use ADT\Datagrid\Model\Export\ExcelExportGenerator;
use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\DI\CompilerExtension;

/**
 * Export dat resi adt/exporter (audit ExportLog + sync/background doruceni) -
 * drivejsi GridExport entita, DatagridService i ProcessExportsCommand cron
 * jsou odstranene. Zde se registruji jen generatory souboru, ktere si
 * exporter extension posbira podle typu.
 */
class DataGridComponentsExtension extends CompilerExtension implements TranslationProviderInterface
{
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('excelExportGenerator'))
			->setType(ExcelExportGenerator::class);
		$builder->addDefinition($this->prefix('csvExportGenerator'))
			->setType(CsvExportGenerator::class);
	}

	public function getTranslationResources(): array
	{
		return [__DIR__ . '/../lang'];
	}
}
