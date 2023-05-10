<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi;

use ArrayObject;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mango.openapi.security_schemes_processor')]
interface SecuritySchemesProcessor
{
    public function __invoke(ArrayObject $securitySchemes): ArrayObject;
}
