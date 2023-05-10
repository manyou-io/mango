<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;

use ApiPlatform\OpenApi\OpenApi;
use Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

#[AsTaggedItem(priority: 30)]
class ProcessSchemas implements OpenApiProcessor
{
    /** @param SchemasProcessor[] $schemasProcessors */
    public function __construct(
        #[TaggedIterator('mango.openapi.schemas_processor')]
        private iterable $schemasProcessors,
    ) {
    }

    public function __invoke(OpenApi $api): OpenApi
    {
        $schemas = $api->getComponents()->getSchemas();

        foreach ($this->schemasProcessors as $schemasProcessor) {
            $schemas = $schemasProcessor($schemas);
        }

        return $api->withComponents($api->getComponents()->withSchemas($schemas));
    }
}
