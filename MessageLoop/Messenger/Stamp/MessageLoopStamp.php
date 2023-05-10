<?php

declare(strict_types=1);

namespace Manyou\Mango\MessageLoop\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Ulid;

class MessageLoopStamp implements StampInterface
{
    public function __construct(
        public string $key,
        public Ulid $loopId,
        public int $delay,
    ) {
    }
}
