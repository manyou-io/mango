<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\SchemasProcessor;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;

use function array_map;
use function array_merge;

trait BuildSchemaDefinitions
{
    use SetSchemaDefinitions;

    public function __construct(private SchemaFactoryInterface $jsonSchemaFactory)
    {
    }

    private function buildSchema(array $params): array
    {
        $params += [
            'format' => 'json',
            'type' => Schema::TYPE_OUTPUT,
            'operation' => null,
            'schema' => new Schema(Schema::VERSION_OPENAPI),
            'forceCollection' => false,
        ];

        return $this->jsonSchemaFactory
            ->buildSchema(...$params)
            ->getDefinitions()
            ->getArrayCopy();
    }

    abstract private function getBuildSchemaParams(): array;

    private function getDefinitions(): array
    {
        return array_merge(...array_map($this->buildSchema(...), $this->getBuildSchemaParams()));
    }
}
