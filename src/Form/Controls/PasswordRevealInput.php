<?php

declare(strict_types=1);

namespace ADT\Datagrid\Form\Controls;

use Nette\Forms\Container;
use Nette\Forms\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Html;

class PasswordRevealInput extends TextInput
{
	public const string INPUT_CLASS = 'toggle-password-input';
	public const string TOGGLE_CLASS = 'toggle-password-reveal';

	private static bool $scriptRendered = false;

	public function __construct($label = null, ?int $maxLength = null)
	{
		parent::__construct($label, $maxLength);

		$this->setHtmlType('password');
		$this->getControlPrototype()->appendAttribute('class', self::INPUT_CLASS);
	}

	public function getControl(): Html
	{
		$input = parent::getControl();

		$button = Html::el('button')
			->setAttribute('type', 'button')
			->setAttribute('tabindex', '-1')
			->appendAttribute('class', 'btn btn-outline-secondary ' . self::TOGGLE_CLASS)
			->addHtml(Html::el('i')->setAttribute('class', 'fas fa-eye'));

		$group = Html::el('div')
			->setAttribute('class', 'input-group')
			->addHtml($input)
			->addHtml($button);

		if (!self::$scriptRendered) {
			self::$scriptRendered = true;
			$group->addHtml(self::getScript());
		}

		return $group;
	}

	public static function addPasswordReveal(Container $container, string $name, $label = null): self
	{
		$component = new self($label);
		$container->addComponent($component, $name);
		return $component;
	}

	public static function register(): void
	{
		Form::extensionMethod('addPasswordReveal', [self::class, 'addPasswordReveal']);
		Container::extensionMethod('addPasswordReveal', [self::class, 'addPasswordReveal']);
	}

	private static function getScript(): Html
	{
		$js = <<<JS
		(function () {
			if (window.__adtPasswordRevealInitialized) {
				return;
			}
			window.__adtPasswordRevealInitialized = true;

			var inputSelector = 'input.{INPUT_CLASS}';

			function mask(input) {
				if (input.dataset.passwordRevealMasked === '1') {
					return;
				}
				input.dataset.passwordRevealMasked = '1';
				input.type = 'password';
			}

			function maskAll() {
				document.querySelectorAll(inputSelector).forEach(mask);
			}

			document.addEventListener('click', function (e) {
				var button = e.target.closest('.{TOGGLE_CLASS}');
				if (!button) {
					return;
				}
				e.preventDefault();
				var group = button.closest('.input-group');
				var input = group ? group.querySelector(inputSelector) : null;
				if (!input) {
					return;
				}
				var masked = input.type === 'password';
				input.type = masked ? 'text' : 'password';
				var icon = button.querySelector('i');
				if (icon) {
					icon.classList.toggle('fa-eye', !masked);
					icon.classList.toggle('fa-eye-slash', masked);
				}
			});

			new MutationObserver(maskAll).observe(document.documentElement, { childList: true, subtree: true });
			maskAll();
		})();
		JS;

		$js = strtr($js, [
			'{INPUT_CLASS}' => self::INPUT_CLASS,
			'{TOGGLE_CLASS}' => self::TOGGLE_CLASS,
		]);

		return Html::el('script')->setHtml($js);
	}
}
