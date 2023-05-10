<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\PathsProcessor;

use ApiPlatform\OpenApi\Model\Paths;

trait RemoveOperationParameters
{
    public function __invoke(Paths $paths): Paths
    {
        foreach ($this->getOperations() as $operation) {
            $this->process($paths, $operation['path'], $operation['method']);
        }

        return $paths;
    }

    abstract private function getOperations(): array;

    private function process(Paths $paths, string $path, string $method)
    {
        $pathItem = $paths->getPath($path);

        /** @var Operation $operation **/
        $operation = $pathItem->{'get' . $method}();

        $parameters = [];
        /** @var Parameter $parameter */
        foreach ($operation->getParameters() as $parameter) {
            if ($parameter->getName() === 'page' && $parameter->getIn() === 'query') {
                continue;
            }

            if ($parameter->getIn() === 'path') {
                continue;
            }

            $parameters[] = $parameter;
        }

        $operation = $operation->withParameters($parameters);
        $pathItem  = $pathItem->{'with' . $method}($operation);
        $paths->addPath($path, $pathItem);
    }
}
