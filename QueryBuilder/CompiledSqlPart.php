<?php

declare(strict_types=1);

namespace Manyou\Mango\QueryBuilder;

class CompiledSqlPart
{
    public function __construct(
        public readonly string $sql,
        public readonly array $paramTypes,
        public readonly string $returnType,
    ) {
    }
}
