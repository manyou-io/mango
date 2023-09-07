<?php

declare(strict_types=1);

namespace Mango\Utils;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\MoneyFormatter;
use Money\MoneyParser;
use Money\Parser\DecimalMoneyParser;

use function is_float;
use function number_format;

class MoneyCaster
{
    private static ?MoneyFormatter $moneyFormatter = null;

    private static ?MoneyParser $moneyParser = null;

    private static function loadMoneyParser(): MoneyParser
    {
        if (self::$moneyParser !== null) {
            return self::$moneyParser;
        }

        return self::$moneyParser = new DecimalMoneyParser(new ISOCurrencies());
    }

    private static function loadMoneyFormatter(): MoneyFormatter
    {
        if (self::$moneyFormatter !== null) {
            return self::$moneyFormatter;
        }

        return self::$moneyFormatter = new DecimalMoneyFormatter(new ISOCurrencies());
    }

    public static function fromDecimal(string|float $amount, string $currency): Money
    {
        $currency = new Currency($currency);
        if (is_float($amount)) {
            $subunit = (new ISOCurrencies())->subunitFor($currency);
            $amount  = number_format($amount, $subunit, '.', '');
        }

        return self::loadMoneyParser()->parse($amount, $currency);
    }

    public static function fromArray(array $money): Money
    {
        return new Money($money['amount'], new Currency($money['currency']));
    }

    public static function toDecimal(Money $money): string
    {
        return self::loadMoneyFormatter()->format($money);
    }

    public static function subunitFor(Money $money): int
    {
        return (new ISOCurrencies())->subunitFor($money->getCurrency());
    }

    public static function toArray(Money $money): array
    {
        return [
            'amount' => (int) $money->getAmount(),
            'currency' => $money->getCurrency()->getCode(),
        ];
    }
}
