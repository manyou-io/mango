<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi;

use ApiPlatform\OpenApi\Model\Paths;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mango.openapi.paths_processor')]
interface PathsProcessor
{
    public function __invoke(Paths $paths): Paths;
}
