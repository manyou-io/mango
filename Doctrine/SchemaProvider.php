<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaProvider as SchemaProviderInterface;
use InvalidArgumentException;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

use function array_merge;
use function is_string;

class SchemaProvider implements SchemaProviderInterface
{
    private Schema $schema;

    private array $tables = [];

    /** @param TableProvider[] $tableProviders */
    public function __construct(
        private Connection $connection,
        #[TaggedIterator('mango.doctrine.table_provider')]
        private iterable $tableProviders,
    ) {
        $this->createSchema();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function toSql(): array
    {
        return $this->createSchema()->toSql($this->connection->getDatabasePlatform());
    }

    public function toDropSql(): array
    {
        return $this->createSchema()->toDropSql($this->connection->getDatabasePlatform());
    }

    public function createSchema(): Schema
    {
        if (isset($this->schema)) {
            return $this->schema;
        }

        $schemaManager = $this->connection->createSchemaManager();

        $schema = new Schema(schemaConfig: $schemaManager->createSchemaConfig());

        foreach ($this->tableProviders as $tableProvider) {
            $table = $tableProvider($schema);

            $this->tables[$table->name] = $table;
        }

        return $this->schema = $schema;
    }

    public function createQuery(): Query
    {
        return new Query($this->connection, $this);
    }

    public function getTable(string $name): Table
    {
        return $this->tables[$name];
    }

    public function executeMergedQuery(string|Query ...$args): Result
    {
        if ($args === []) {
            throw new InvalidArgumentException('Arguments required.');
        }

        $sql    = '';
        $params = [];
        $types  = [];

        foreach ($args as $arg) {
            if (is_string($arg)) {
                $sql .= $arg;
                continue;
            }

            if ($arg instanceof Query) {
                $sql     .= $arg->getSQL();
                $params[] = $arg->getParameters();
                $types[]  = $arg->getParameterTypes();
                continue;
            }
        }

        return $this->connection->executeQuery($sql, array_merge(...$params), array_merge(...$types));
    }
}
