<?php

declare(strict_types=1);

namespace Manyou\Mango\HttpKernel;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[AutoconfigureTag('mango.http_kernel.dto_initializer')]
interface DtoInitializerInterface
{
    public function initialize(Request $request, ArgumentMetadata $argument): ?object;
}
