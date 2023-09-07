<?php

declare(strict_types=1);

namespace Mango\Query;

use Doctrine\DBAL\Types\Type;

class ResultColumnMetadata
{
    public function __construct(
        public readonly string $group,
        public readonly string $name,
        public readonly Type $type,
    ) {
    }
}
