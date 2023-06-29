<?php

declare(strict_types=1);

namespace Manyou\Mango\Jose;

interface JWKSLoader
{
    public function __invoke(): array;
}
