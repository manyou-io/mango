<?php

declare(strict_types=1);

namespace Manyou\Mango\HttpKernel;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AsDtoInitializer
{
    public function __construct(
        public ?string $class = null,
        public ?string $method = null,
    ) {
    }
}
