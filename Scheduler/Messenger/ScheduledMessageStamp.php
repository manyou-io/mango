<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Messenger;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class ScheduledMessageStamp implements NonSendableStampInterface
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
