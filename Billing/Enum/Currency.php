<?php

declare(strict_types=1);

namespace Manyou\Mango\Billing\Enum;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case JPY = 'JPY';
    case GBP = 'GBP';
    case CNY = 'CNY';
    case AUD = 'AUD';
    case HKD = 'HKD';
}
