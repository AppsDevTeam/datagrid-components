<?php

namespace ADT\Datagrid\Model\Latte;

use Nette\Application\AbortException;
use Nette\Application\UI\Presenter;

trait RedrawSidePanel
{
	abstract public function getPresenter(): ?Presenter;
	abstract public function getSnippetId(string $name): string;

	/**
	 * @throws AbortException
	 */
	public function redrawSidePanel(string $sidePanelName): void
	{
		$this->getPresenter()->payload->snippets[$this->getSnippetId('sidePanel')] = $this[$sidePanelName]->renderToString();
		$this->getPresenter()->sendPayload();
	}
}
