<?php

declare(strict_types=1);

namespace Mango\Utils;

use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\MoneyFormatter;
use NumberFormatter;

class MoneyFormatterFactory
{
    private static ISOCurrencies $currencies;
    private static array $moneyFormatters = [];

    private static function getCurrencies(): ISOCurrencies
    {
        return self::$currencies ??= new ISOCurrencies();
    }

    public static function create(string $locale): MoneyFormatter
    {
        return self::$moneyFormatters[$locale] ??=
            new IntlMoneyFormatter(
                new NumberFormatter($locale, NumberFormatter::CURRENCY),
                self::getCurrencies(),
            );
    }
}
