<?php

declare(strict_types=1);

namespace Mango\TaskQueue\Messenger\Stamp;

use Closure;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Uid\Ulid;

class ScheduleTaskStamp implements NonSendableStampInterface
{
    public function __construct(private Closure $callback)
    {
    }

    public function callback(Ulid $id): void
    {
        ($this->callback)($id);
    }
}
