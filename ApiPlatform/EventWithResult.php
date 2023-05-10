<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform;

interface EventWithResult
{
    public function getEventResult(): mixed;
}
