<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi;

use ArrayObject;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mango.openapi.schemas_processor')]
interface SchemasProcessor
{
    public function __invoke(ArrayObject $schemas): ArrayObject;
}
