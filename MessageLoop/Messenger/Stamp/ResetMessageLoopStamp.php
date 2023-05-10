<?php

declare(strict_types=1);

namespace Manyou\Mango\MessageLoop\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Uid\Ulid;

class ResetMessageLoopStamp implements NonSendableStampInterface
{
    public function __construct(
        public string $key,
        public Ulid $loopId,
        public int $delay = 1000,
    ) {
    }

    public function toMessageLoopStamp(): MessageLoopStamp
    {
        return new MessageLoopStamp($this->key, $this->loopId, $this->delay);
    }
}
