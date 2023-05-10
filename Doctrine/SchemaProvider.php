<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaProvider as SchemaProviderInterface;
use InvalidArgumentException;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Exception\RowNumUnmatched;
use PDOException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Throwable;
use UnexpectedValueException;

use function array_map;
use function array_merge;
use function implode;
use function is_string;
use function strpos;
use function substr;

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

    public function transactional(Closure $func)
    {
        $this->connection->beginTransaction();
        try {
            $res = $func($this);
            $this->connection->commit();

            return $res;
        } catch (Throwable $e) {
            try {
                $this->connection->rollBack();
            } catch (PDOException) {
            }

            throw $e;
        }
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

    public function updateSetFrom(Query $update, Query $select, ?int $expectedRowNum = null): int
    {
        $sql       = $update->getSQL();
        $selectSql = $select->getSQL();

        if (false === $offset = strpos($selectSql, ' FROM ')) {
            throw new UnexpectedValueException('Invalid select query.');
        }

        $sql .= substr($selectSql, $offset);

        $params[] = $update->getParameters();
        $params[] = $select->getParameters();

        $types[] = $update->getParameterTypes();
        $types[] = $select->getParameterTypes();

        $rowNum = $this->connection->executeStatement($sql, array_merge(...$params), array_merge(...$types));

        if ($rowNum !== ($expectedRowNum ?? $rowNum)) {
            throw RowNumUnmatched::create($expectedRowNum, $rowNum);
        }

        return $rowNum;
    }

    public function getTableQuotedName(string $name): string
    {
        return $this->schema->getTable($name)->getQuotedName($this->connection->getDatabasePlatform());
    }

    public function onConflictDoUpdate(Query $insert, array $conflict, array $update, ?int $expectedRowNum = null)
    {
        $sql = $insert->getSQL();

        $conflict = array_map(static fn ($n) => $insert->quoteColumn($n), $conflict);

        $sql .= ' ON CONFLICT (' . implode(', ', $conflict) . ') DO UPDATE';

        $update = $insert->insertToUpdate($update);

        $updateSql = $update->getSQL();
        if (false === $offset = strpos($updateSql, ' SET ')) {
            throw new UnexpectedValueException('Invalid update query.');
        }

        $sql .= substr($updateSql, $offset);

        $params[] = $insert->getParameters();
        $params[] = $update->getParameters();

        $types[] = $insert->getParameterTypes();
        $types[] = $update->getParameterTypes();

        $rowNum = $this->connection->executeStatement($sql, array_merge(...$params), array_merge(...$types));

        if ($rowNum !== ($expectedRowNum ?? $rowNum)) {
            throw RowNumUnmatched::create($expectedRowNum, $rowNum);
        }

        return $rowNum;
    }
}
