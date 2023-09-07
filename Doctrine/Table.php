<?php

declare(strict_types=1);

namespace Mango\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table as DBALTable;

use function lcfirst;
use function preg_replace_callback;
use function strtoupper;

/**
 * @method $this setPrimaryKey(string[] $columnNames, string|false $indexName = false)
 * @method $this addUniqueIndex(string[] $columnNames, string|null $indexName = null, mixed[] $options = [])
 * @method $this addIndex(string[] $columnNames, string|null $indexName = null, string[] $flags = [], mixed[] $options = [])
 * @method $this addForeignKeyConstraint(string $foreignTableName, string[] $localColumnNames, string[] $foreignColumnNames, mixed[] $options = [], string|null $constraintName = null)
 * @method string getQuotedName(AbstractPlatform $platform)
 * @method string getName()
 */
class Table
{
    private array $columnsByAlias = [];

    public function __construct(
        private DBALTable $table,
    ) {
    }

    public function getColumn(string $name): Column
    {
        return $this->columnsByAlias[$name] ?? $this->table->getColumn($name);
    }

    public function addColumn($name, $typeName, array $options = [], ?string $alias = null): Column
    {
        $alias ??= $this->toCamelCase($name);

        return $this->columnsByAlias[$alias] =  $this->table->addColumn($name, $typeName, $options);
    }

    private function toCamelCase(string $name): string
    {
        $camelCasedName = preg_replace_callback('/(^|_|\.)+(.)/', static fn ($match) => ('.' === $match[1] ? '_' : '') . strtoupper($match[2]), $name);

        return lcfirst($camelCasedName);
    }

    public function getColumnsByAlias(): array
    {
        return $this->columnsByAlias;
    }

    public function __call($name, $args)
    {
        $ret = $this->table->{$name}(...$args);

        return $ret === $this->table ? $this : $ret;
    }
}
