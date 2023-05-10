<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform\OpenApi;

use ApiPlatform\OpenApi\Model\Operation;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mango.openapi.operation_processor')]
interface OperationProcessor
{
    public function __invoke(Operation $operation, string $path, string $method): Operation;
}
