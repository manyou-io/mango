<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;

use ApiPlatform\OpenApi\OpenApi;
use Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

#[AsTaggedItem(priority: 20)]
class ProcessPaths implements OpenApiProcessor
{
    /** @param PathsProcessor[] $pathsProcessors */
    public function __construct(
        #[TaggedIterator('mango.openapi.paths_processor')]
        private iterable $pathsProcessors,
    ) {
    }

    public function __invoke(OpenApi $api): OpenApi
    {
        $paths = $api->getPaths();

        foreach ($this->pathsProcessors as $pathsProcessor) {
            $paths = $pathsProcessor($paths);
        }

        return $api->withPaths($paths);
    }
}
