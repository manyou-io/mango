<?php

declare(strict_types=1);

namespace Mango\Utils;

use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\MoneyFormatter;
use NumberFormatter;

class IntlMoneyFormatterFactory
{
    private ISOCurrencies $currencies;
    private array $moneyFormatters = [];

    public function __construct()
    {
        $this->currencies = new ISOCurrencies();
    }

    public function create(string $locale): MoneyFormatter
    {
        if ($this->moneyFormatters[$locale] ?? null) {
            return $this->moneyFormatters[$locale];
        }

        return $this->moneyFormatters[$locale] = new IntlMoneyFormatter(new NumberFormatter($locale, NumberFormatter::CURRENCY), $this->currencies);
    }
}
