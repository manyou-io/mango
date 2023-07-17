<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table as DoctrineTable;

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
    private array $columnMap = [];

    private DoctrineTable $table;

    public function __construct(
        Schema $schema,
        public readonly string $name,
    ) {
        $this->table = $schema->createTable($name);
    }

    public function getColumn(string $name): Column
    {
        return $this->columnMap[$name] ?? $this->table->getColumn($name);
    }

    public function addColumn(string $name, string $typeName, array $options = [], ?string $alias = null): Column
    {
        $this->columnMap[$alias ?? $name] = $column = $this->table->addColumn($name, $typeName, $options);

        return $column;
    }

    public function getColumns(): array
    {
        return $this->columnMap;
    }

    public function __call($name, $arguments)
    {
        $returnValue = $this->table->{$name}(...$arguments);

        return $returnValue === $this->table ? $this : $returnValue;
    }
}
