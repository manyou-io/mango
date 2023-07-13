<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class ScheduledMessageStamp implements StampInterface
{
    public function __construct(
        private string $key,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
