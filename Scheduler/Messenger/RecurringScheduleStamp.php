<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Messenger;

use Symfony\Component\Scheduler\RecurringMessage;

class RecurringScheduleStamp
{
    public function __construct(
        public readonly string $key,
        public readonly RecurringMessage $recurringMessage,
    ) {
    }
}
