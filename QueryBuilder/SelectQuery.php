<?php

declare(strict_types=1);

namespace Manyou\Mango\QueryBuilder;

class SelectQuery
{
    public function __construct(
        private string $from,
        private ?string $fromAlias = null,
    ) {
    }

    private array $joins = [];

    public function join(string $table, string $alias, SqlPart $condition): self
    {
        $this->joins[] = new Join($table, $alias, $condition);

        return $this;
    }
}
