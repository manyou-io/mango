<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform;

interface DtoInitializerInterface
{
    public function initialize(string $inputClass, array $attributes): ?object;
}
