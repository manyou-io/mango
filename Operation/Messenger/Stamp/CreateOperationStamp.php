<?php

declare(strict_types=1);

namespace Manyou\Mango\Operation\Messenger\Stamp;

use Closure;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Uid\Ulid;

class CreateOperationStamp implements NonSendableStampInterface
{
    public function __construct(private Closure $callback)
    {
    }

    public function callback(Ulid $id): void
    {
        ($this->callback)($id);
    }
}
