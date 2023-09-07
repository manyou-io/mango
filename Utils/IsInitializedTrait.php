<?php

declare(strict_types=1);

namespace Mango\Utils;

use ReflectionProperty;

trait IsInitializedTrait
{
    private static array $reflectionProperties = [];

    public function isInitialized(string $property): bool
    {
        $ref = self::$reflectionProperties[$property] ??= new ReflectionProperty($this, $property);

        return $ref->isInitialized($this);
    }
}
