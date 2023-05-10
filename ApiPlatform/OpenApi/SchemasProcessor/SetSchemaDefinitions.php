<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;

use ArrayObject;

trait SetSchemaDefinitions
{
    abstract private function getDefinitions(): array;

    public function __invoke(ArrayObject $schemas): ArrayObject
    {
        $schemas->exchangeArray($this->getDefinitions() + $schemas->getArrayCopy());

        return $schemas;
    }
}
