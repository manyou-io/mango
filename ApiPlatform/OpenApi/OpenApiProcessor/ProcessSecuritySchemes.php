<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;

use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Manyou\Mango\ApiPlatform\OpenApi\OpenApiProcessor;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

#[AsTaggedItem(priority: 40)]
class ProcessSecuritySchemes implements OpenApiProcessor
{
    /** @param SecuritySchemesProcessor[] $securitySchemesProcessors */
    public function __construct(
        #[TaggedIterator('mango.openapi.security_schemes_processor')]
        private iterable $securitySchemesProcessors,
    ) {
    }

    public function __invoke(OpenApi $api): OpenApi
    {
        $components      = $api->getComponents();
        $securitySchemes = $components->getSecuritySchemes() ?? new ArrayObject();

        foreach ($this->securitySchemesProcessors as $securitySchemesProcessor) {
            $securitySchemes = $securitySchemesProcessor($securitySchemes);
        }

        return $api->withComponents($components->withSecuritySchemes($securitySchemes));
    }
}
