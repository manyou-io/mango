<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;

use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

#[AsTaggedItem(priority: 10)]
class ProcessOperations implements OpenApiProcessor
{
    /** @param OperationProcessor[] $operationProcessors */
    public function __construct(
        #[TaggedIterator('mango.openapi.operation_processor')]
        private iterable $operationProcessors,
    ) {
    }

    public function __invoke(OpenApi $api): OpenApi
    {
        $paths = new Paths();

        foreach ($api->getPaths()->getPaths() as $path => $pathItem) {
            foreach (PathItem::$methods as $method) {
                $operation = $pathItem->{'get' . $method}();
                if ($operation !== null) {
                    foreach ($this->operationProcessors as $operationProcessor) {
                        $operation = $operationProcessor($operation, $path, $method);
                    }

                    $pathItem = $pathItem->{'with' . $method}($operation);
                }
            }

            $paths->addPath($path, $pathItem);
        }

        return $api->withPaths($paths);
    }
}
