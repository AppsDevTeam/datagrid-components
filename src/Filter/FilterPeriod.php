<?php declare(strict_types = 1);

namespace ADT\Datagrid\Filter;

use Contributte\Datagrid\Exception\DatagridDateTimeHelperException;
use Contributte\Datagrid\Filter\IFilterDate;
use Contributte\Datagrid\Filter\OneColumnFilter;
use Contributte\Datagrid\Utils\DateTimeHelper;
use DateTimeInterface;
use Nette\Forms\Container;
use Traversable;

class FilterPeriod extends OneColumnFilter implements IFilterDate
{
	public const string CUSTOM = 'custom';

	public const string DEFAULT_PERIOD = 'month';

	public const array PERIODS = [
		'day' => '-1 day',
		'week' => '-7 days',
		'month' => '-1 month',
		'quarter' => '-3 months',
		self::CUSTOM => null,
	];

	protected ?string $template = 'datagrid_filter_period.latte';

	protected ?string $type = 'period';

	public const string RANGE_DELIMITER = ' - ';

	public const string PICKER_FORMAT = 'D.M.YYYY HH:mm';

	public const array PHP_FORMATS = ['j.n.Y H:i', 'j.n.Y'];

	protected array $format = ['j.n.Y', 'd.m.yyyy'];

	public function addToFormContainer(Container $container): void
	{
		$container = $container->addContainer($this->key);

		$range = $container->addText('range', $this->name)
			->setHtmlAttribute('readonly', 'readonly')
			->setHtmlAttribute('data-adt-daterange', $this->getPickerOptions());

		$this->addAttributes($range);

		if ($this->grid->hasAutoSubmit()) {
			$range->setHtmlAttribute('data-autosubmit-change', true);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function getPickerOptions(): array
	{
		return [
			'locale' => [
				'format' => self::PICKER_FORMAT,
				'applyLabel' => $this->translatePeriod('apply'),
				'cancelLabel' => $this->translatePeriod('cancel'),
				'customRangeLabel' => $this->translatePeriod(self::CUSTOM),
			],
			'ranges' => $this->getPickerRanges(),
		];
	}

	/**
	 * @return array<string, array{label: string, range: array{0: string, 1: string}}>
	 */
	protected function getPickerRanges(): array
	{
		$now = new \DateTimeImmutable();

		$ranges = [];
		foreach (self::PERIODS as $_period => $_modify) {
			if ($_modify === null) {
				continue;
			}

			$ranges[$_period] = [
				'label' => $this->translatePeriod($_period),
				'range' => [
					$now->modify($_modify)->format(self::PHP_FORMATS[0]),
					$now->format(self::PHP_FORMATS[0]),
				],
			];
		}

		return $ranges;
	}

	protected function translatePeriod(string $key): string
	{
		return $this->grid->getTranslator()->translate('ublaboo_datagrid.period.' . $key);
	}

	public function getPeriod(): string
	{
		$text = $this->getRawRange();

		foreach (self::PERIODS as $_period => $_modify) {
			if ($_modify !== null && $this->translatePeriod($_period) === $text) {
				return $_period;
			}
		}

		return str_contains($text, self::RANGE_DELIMITER) ? self::CUSTOM : self::DEFAULT_PERIOD;
	}

	public function getDefaultRangeText(): string
	{
		return $this->translatePeriod(self::DEFAULT_PERIOD);
	}

	private function getRawRange(): string
	{
		$value = $this->getValues()['range'] ?? '';

		return is_string($value) ? trim($value) : '';
	}

	/**
	 * @return array{0: ?DateTimeInterface, 1: ?DateTimeInterface}
	 */
	public function getRange(): array
	{
		$period = $this->getPeriod();

		if ($period !== self::CUSTOM) {
			return [(new \DateTimeImmutable())->modify(self::PERIODS[$period]), null];
		}

		[$from, $to] = $this->parseRange($this->getValues()['range'] ?? null);

		if ($from === null && $to === null) {
			return [(new \DateTimeImmutable())->modify(self::PERIODS[self::DEFAULT_PERIOD]), null];
		}

		return [$from, $to];
	}

	public function getRangeText(): string
	{
		return $this->getRawRange() ?: $this->getDefaultRangeText();
	}

	/**
	 * @return array{0: ?DateTimeInterface, 1: ?DateTimeInterface}
	 */
	private function parseRange(mixed $value): array
	{
		if (!is_string($value) || !str_contains($value, self::RANGE_DELIMITER)) {
			return [null, null];
		}

		[$fromText, $toText] = array_map('trim', explode(self::RANGE_DELIMITER, $value, 2));

		$from = $this->parseDate($fromText);
		$to = $this->parseDate($toText);

		return [
			$from !== null && !$this->hasTime($fromText) ? $from->setTime(0, 0, 0) : $from,
			$to !== null && !$this->hasTime($toText) ? $to->setTime(23, 59, 59) : $to,
		];
	}

	private function hasTime(string $value): bool
	{
		return str_contains($value, ':');
	}

	public function setFormat(string $phpFormat, string $jsFormat): IFilterDate
	{
		$this->format = [$phpFormat, $jsFormat];

		return $this;
	}

	public function getPhpFormat(): string
	{
		return $this->format[0];
	}

	public function getJsFormat(): string
	{
		return $this->format[1];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getValues(): array
	{
		if (!$this->isValueSet()) {
			return [];
		}

		$value = $this->getValue();

		if ($value instanceof Traversable) {
			$value = iterator_to_array($value);
		}

		return is_array($value) ? $value : [];
	}

	private function parseDate(mixed $value): ?\DateTime
	{
		if (!is_string($value) || $value === '') {
			return null;
		}

		try {
			return DateTimeHelper::tryConvertToDate($value, self::PHP_FORMATS);
		} catch (DatagridDateTimeHelperException) {
			return null;
		}
	}
}
