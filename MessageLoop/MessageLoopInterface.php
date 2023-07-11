<?php

declare(strict_types=1);

namespace Manyou\Mango\MessageLoop;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mango.message_loop')]
interface MessageLoopInterface
{
    public function getMessage(): object;

    public function getDelay(): int;
}
