<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use ErrorException;
use Generator;
use InvalidArgumentException;
use RuntimeException;

use function array_fill;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_string;
use function sprintf;

/**
 * @method $this where($predicates)
 * @method $this andWhere($where)
 * @method $this orWhere($where)
 * @method $this setMaxResults(int|null $maxResults)
 * @method $this orderBy(string $sort, string|null $order = null)
 * @method array getParameters()
 * @method Type[] getParameterTypes()
 */
class Query
{
    public const EQ  = '=';
    public const NEQ = '<>';
    public const LT  = '<';
    public const LTE = '<=';
    public const GT  = '>';
    public const GTE = '>=';

    private QueryBuilder $builder;

    private AbstractPlatform $platform;

    private Result $result;

    private array $selects = [];

    /** @var Type[] */
    private array $resultTypeMap = [];

    /** @var string[] */
    private array $resultTableAliasMap = [];

    /** @var string[] */
    private array $resultColumnAliasMap = [];

    private int $resultAliasCounter = 0;

    /** @var Table[] Tracking table alias for reference in expressions */
    private array $selectTableMap = [];

    public function __construct(
        private Connection $connection,
        private SchemaProvider $schema,
    ) {
        $this->builder  = $this->connection->createQueryBuilder();
        $this->platform = $this->connection->getDatabasePlatform();
    }

    public function insert(string $into, array $data)
    {
        $table = $this->schema->getTable($into);

        $this->builder->insert($into);

        $values = [];
        foreach ($data as $key => $value) {
            try {
                $column = $table->getColumn($key);
            } catch (SchemaException) {
                // ignore unknown columns
                continue;
            }

            $values[$column->getQuotedName($this->platform)] = $this->builder->createPositionalParameter(
                $value,
                $column->getType(),
            );
        }

        $this->builder->values($values);

        return $this;
    }

    public function bulkInsert(string $into, array ...$records): void
    {
        if (! isset($records[0])) {
            return;
        }

        $table = $this->schema->getTable($into);

        $columns = [];
        $types   = [];
        $params  = [];

        foreach (array_keys($records[0]) as $key) {
            $column    = $table->getColumn($key);
            $columns[] = $column->getQuotedName($this->platform);
            $types[]   = $column->getType();
        }

        $recordsCount = count($records);
        $columnsCount = count($columns);

        $types = array_merge(...array_fill(0, $recordsCount, $types));

        foreach ($records as $record) {
            $params[] = array_values($record);
        }

        $params = array_merge(...$params);

        if (count($params) !== count($types)) {
            throw new InvalidArgumentException('Invalid record values.');
        }

        $sql = $this->platform instanceof OraclePlatform
            ? $this->getBulkInsertSQLForOracle($into, $columns, $columnsCount, $recordsCount)
            : $this->getBulkInsertSQL($into, $columns, $columnsCount, $recordsCount);

        $rowNum = $this->connection->executeStatement($sql, $params, $types);

        if ($rowNum === $recordsCount) {
            return;
        }

        throw new RuntimeException(sprintf('Bulk insert failed: row num (%d) !== records count (%d)', $rowNum, $recordsCount));
    }

    private function getBulkInsertSQL(string $into, array $columns, int $columnsCount, int $recordsCount): string
    {
        $sql  = "INSERT INTO {$into} ";
        $sql .= '(' . implode(',', $columns) . ')';
        $sql .= ' VALUES ';

        $values = '(' . implode(',', array_fill(0, $columnsCount, '?')) . ')';

        return $sql . implode(',', array_fill(0, $recordsCount, $values));
    }

    private function getBulkInsertSQLForOracle(string $into, array $columns, int $columnsCount, int $recordsCount): string
    {
        $sql = 'INSERT ALL ';

        $columns = '(' . implode(',', $columns) . ')';
        $values  = '(' . implode(',', array_fill(0, $columnsCount, '?')) . ')';

        $sql .= implode(' ', array_fill(0, $recordsCount, "INTO {$into} {$columns} VALUES {$values}"));
        $sql .= ' SELECT 1 FROM dual';

        return $sql;
    }

    private function getResultAlias(): string
    {
        return 'c' . $this->resultAliasCounter++;
    }

    public function selectFrom(string|array $from, ?string ...$selects)
    {
        $this->addSelects($this->addFrom($from, [$this->builder, 'from']), $selects);

        return $this;
    }

    public function join(string $fromAlias, string $joinTable, string $joinAlias, string $on, ?string ...$selects)
    {
        $table = $this->schema->getTable($joinTable);
        $this->builder->join($fromAlias, $joinTable, $joinAlias, $on);

        $this->selectTableMap[$joinAlias] = $table;
        $this->addSelects($joinAlias, $selects);

        return $this;
    }

    public function leftJoin(string $fromAlias, string $joinTable, string $joinAlias, string $on, ?string ...$selects)
    {
        $table = $this->schema->getTable($joinTable);
        $this->builder->leftJoin($fromAlias, $joinTable, $joinAlias, $on);

        $this->selectTableMap[$joinAlias] = $table;
        $this->addSelects($joinAlias, $selects);

        return $this;
    }

    public function update(string|array $from, array $data = [])
    {
        $fromAlias = $this->addFrom($from, [$this->builder, 'update']);

        foreach ($data as $column => $value) {
            $this->builder->set(...$this->bind($fromAlias, $column, $value));
        }

        return $this;
    }

    /** @return string The from alias */
    private function addFrom(string|array $from, callable $builderCall): string
    {
        [$fromTable, $fromAlias] = is_string($from) ? [$from, null] : $from;

        $table = $this->schema->getTable($fromTable);
        $builderCall($fromTable, $fromAlias);

        $fromAlias ??= $fromTable;

        $this->selectTableMap[$fromAlias] = $table;

        return $fromAlias;
    }

    /** @return Column[] */
    private function getSelectColumns(Table $table, array $selects): Generator
    {
        if ($selects === []) {
            return yield from $table->getColumns();
        }

        if ($selects[0] === '*') {
            foreach ($table->getColumns() as $columnAlias => $column) {
                yield ($selects[$columnAlias] ?? $columnAlias) => $column;
            }

            return;
        }

        foreach ($selects as $alias => $name) {
            yield $alias => $table->getColumn($name);
        }
    }

    private function addSelects(string $tableAlias, array $selects)
    {
        if (array_key_exists(0, $selects) && $selects[0] === null) {
            return;
        }

        $columns = $this->getSelectColumns($this->selectTableMap[$tableAlias], $selects);

        foreach ($columns as $columnAlias => $column) {
            $columnAlias = is_string($columnAlias)
                // Named parameter: `$this->selectFrom('table', alias1: 'column1')`
                ? $columnAlias
                // Positional parameter: `$this->selectFrom('table', 'column1', 'column2')`
                : $selects[$columnAlias];

            $resultAlias = $this->getResultAlias();

            $this->resultTypeMap[$resultAlias]        = $type = $column->getType();
            $this->resultTableAliasMap[$resultAlias]  = $tableAlias;
            $this->resultColumnAliasMap[$resultAlias] = $columnAlias;

            $this->selects[] =
                $type->convertToPHPValueSQL(
                    $tableAlias . '.' . $column->getQuotedName($this->platform),
                    $this->platform,
                )
                . ' ' . $this->platform->quoteSingleIdentifier($resultAlias);
        }
    }

    private function getQueryResult(): Result
    {
        if (isset($this->result)) {
            return $this->result;
        }

        if ($this->selects !== []) {
            $this->builder->select(...$this->selects);
        }

        return $this->result = $this->builder->executeQuery();
    }

    public function getSQL(): string
    {
        if ($this->selects !== []) {
            $this->builder->select(...$this->selects);
        }

        return $this->builder->getSQL();
    }

    private function convertResultValues(array $result): array
    {
        foreach ($result as $resultAlias => $value) {
            $value = $this->resultTypeMap[$resultAlias]
                ->convertToPHPValue($value, $this->platform);

            $tableAlias  = $this->resultTableAliasMap[$resultAlias];
            $columnAlias = $this->resultColumnAliasMap[$resultAlias];

            $row[$tableAlias][$columnAlias] = $value;
        }

        return $row;
    }

    public function fetchAssociative(): array|false
    {
        if (false !== $row = $this->getQueryResult()->fetchAssociative()) {
            return $this->convertResultValues($row);
        }

        return $row;
    }

    public function fetchAllAssociative(): array
    {
        $rows = $this->getQueryResult()->fetchAllAssociative();

        foreach ($rows as $i => $row) {
            $rows[$i] = $this->convertResultValues($row);
        }

        return $rows;
    }

    public function getBuilder(): QueryBuilder
    {
        return $this->builder;
    }

    public function comparison(string $x, string $operator, $y): string
    {
        try {
            [$tableAlias, $column] = explode('.', $x, 2);
        } catch (ErrorException) {
            throw new InvalidArgumentException(
                'The left hand side of a comparison should be like "<tableAlias>.<column>".',
            );
        }

        return implode(" {$operator} ", $this->bind($tableAlias, $column, $y));
    }

    public function bind(string $tableAlias, string $column, $value): array
    {
        $column = $this->selectTableMap[$tableAlias]->getColumn($column);

        return [
            "{$tableAlias}.{$column->getQuotedName($this->platform)}",
            $this->builder->createPositionalParameter($value, $column->getType()),
        ];
    }

    public function eq(string $x, $y): string
    {
        return $this->comparison($x, self::EQ, $y);
    }

    public function neq(string $x, $y): string
    {
        return $this->comparison($x, self::NEQ, $y);
    }

    public function lt(string $x, $y): string
    {
        return $this->comparison($x, self::LT, $y);
    }

    public function lte(string $x, $y): string
    {
        return $this->comparison($x, self::LTE, $y);
    }

    public function gt(string $x, $y): string
    {
        return $this->comparison($x, self::GT, $y);
    }

    public function gte(string $x, $y): string
    {
        return $this->comparison($x, self::GTE, $y);
    }

    public function executeStatement(): int
    {
        return $this->builder->executeStatement();
    }

    public function __call($name, $arguments)
    {
        $returnValue = $this->builder->{$name}(...$arguments);

        return $returnValue === $this->builder ? $this : $returnValue;
    }
}
