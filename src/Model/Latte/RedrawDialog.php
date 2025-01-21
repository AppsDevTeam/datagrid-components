<?php

namespace ADT\Datagrid\Model\Latte;

use Nette\Application\AbortException;
use Nette\Application\UI\Presenter;

trait RedrawDialog
{
	abstract public function getPresenter(): ?Presenter;
	abstract public function getSnippetId(string $name): string;

	/**
	 * @throws AbortException
	 */
	protected function redrawDialog(string $dialogName): void
	{
		$this->getPresenter()->payload->snippets[$this->getSnippetId('dialog')] = $this[$dialogName]->renderToString();
		$this->getPresenter()->sendPayload();
	}
}
