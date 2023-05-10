<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;

use function array_map;

trait BuildEnumDefinitions
{
    use SetSchemaDefinitions;

    abstract private function getEnums(): array;

    private function getDefinitions(): array
    {
        return array_map($this->buildEnumDefinition(...), $this->getEnums());
    }

    private function buildEnumDefinition(string $enumClassName): array
    {
        return [
            'type' => 'string',
            'enum' => array_map(static fn ($enum) => $enum->value, $enumClassName::cases()),
        ];
    }
}
