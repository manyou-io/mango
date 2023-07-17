<?php

declare(strict_types=1);

namespace Manyou\Mango\QueryBuilder;

interface SqlPart
{
    public function __invoke(Context $context): CompiledSqlPart;
}
