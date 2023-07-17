<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Scheduler\RecurringMessage;

class RecurringScheduleStamp implements StampInterface
{
    public function __construct(
        public readonly string $key,
        public readonly RecurringMessage $recurringMessage,
    ) {
    }
}
