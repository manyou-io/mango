<?php

declare(strict_types=1);

namespace Mango\Twig;

use Mango\Utils\MoneyFormatterFactory;
use Money\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use function locale_get_default;

class MangoExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'formatMoney']),
        ];
    }

    public function formatMoney(Money $money, ?string $locale = null): string
    {
        $locale ??= locale_get_default();

        return MoneyFormatterFactory::create($locale)->format($money);
    }
}
