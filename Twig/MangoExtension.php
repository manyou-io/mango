<?php

declare(strict_types=1);

namespace Manyou\Mango\Twig;

use Manyou\Mango\Utils\IntlMoneyFormatterFactory;
use Money\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use function locale_get_default;

class MangoExtension extends AbstractExtension
{
    public function __construct(private IntlMoneyFormatterFactory $moneyFormatterFactory)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'formatMoney']),
        ];
    }

    public function formatMoney(Money $money, ?string $locale = null): string
    {
        $locale ??= locale_get_default();

        return $this->moneyFormatterFactory->create($locale)->format($money);
    }
}
