<?php

declare(strict_types=1);

namespace Manyou\Mango\Jose;

use Jose\Component\Core\JWKSet;

interface JWKSLoader
{
    public function __invoke(): JWKSet;
}
