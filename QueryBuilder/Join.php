<?php

declare(strict_types=1);

namespace Manyou\Mango\QueryBuilder;

class Join implements SqlPart
{
    public function __construct(
        private string $table,
        private string $alias,
        private SqlPart $condition,
    ) {
    }

    public function __invoke(Context $context): CompiledSqlPart
    {
    }
}
