<?php

declare(strict_types=1);

namespace Manyou\Mango\MessageLoop;

interface MessageLoopInterface
{
    public function getMessage(): object;

    public function getDelay(): int;
}
