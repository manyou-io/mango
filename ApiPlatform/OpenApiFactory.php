<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

#[AsDecorator('api_platform.openapi.factory')]
class OpenApiFactory implements OpenApiFactoryInterface
{
    public const EMPTY_REQUEST_JSON = [
        'application/json' => [
            'schema' => [
                'type' => 'object',
                'nullable' => true,
            ],
        ],
    ];

    /** @param OpenApiProcessor[] $processors */
    public function __construct(
        #[AutowireDecorated] private OpenApiFactoryInterface $inner,
        #[TaggedIterator('mango.openapi.openapi_processor')]
        private iterable $processors,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $api = ($this->inner)($context);

        foreach ($this->processors as $processor) {
            $api = $processor($api);
        }

        return $api;
    }
}
