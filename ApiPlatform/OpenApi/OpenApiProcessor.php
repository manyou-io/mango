<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi;

use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mango.openapi.openapi_processor')]
interface OpenApiProcessor
{
    public function __invoke(OpenApi $api): OpenApi;
}
