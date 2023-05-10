<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;

use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Exception;
use Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Throwable;

use function is_iterable;
use function is_object;
use function method_exists;
use function strlen;
use function substr;

#[AsTaggedItem(priority: 0)]
class RemoveUnusedSchemas implements OpenApiProcessor
{
    public function __invoke(OpenApi $api): OpenApi
    {
        while (true) {
            try {
                $api = $this->filter($api);
            } catch (Throwable $e) {
                if ($e->getMessage() === 'No schemas removed.') {
                    break;
                }

                throw $e;
            }
        }

        return $api;
    }

    private function filter(OpenApi $api): OpenApi
    {
        $paths = $api->getPaths();

        $refs = new ArrayObject();

        foreach ($paths->getPaths() as $pathItem) {
            foreach (PathItem::$methods as $method) {
                $operation = $pathItem->{'get' . $method}();
                $this->countRefs($operation, $refs);
            }
        }

        $schemas = new ArrayObject();
        $this->countRefs($api->getComponents()->getSchemas()->getArrayCopy(), $refs);

        $removed = 0;
        foreach ($api->getComponents()->getSchemas() as $name => $schema) {
            if (isset($refs[$name])) {
                $schemas[$name] = $schema;

                continue;
            }

            $removed++;
        }

        if ($removed === 0) {
            throw new Exception('No schemas removed.');
        }

        return $api->withComponents($api->getComponents()->withSchemas($schemas));
    }

    private function countRefs(mixed $value, ArrayObject $refs): void
    {
        $prefix = strlen('#/components/schemas/');

        if (is_iterable($value)) {
            foreach ($value as $key => $element) {
                if ($key === '$ref') {
                    $refs[substr($element, $prefix)] = true;

                    continue;
                }

                $this->countRefs($element, $refs);
            }
        } elseif (is_object($value)) {
            foreach (['Responses', 'RequestBody', 'Content', 'Schema'] as $property) {
                if (method_exists($value, 'get' . $property)) {
                    $this->countRefs($value->{'get' . $property}(), $refs);
                }
            }
        }
    }
}
