<?php

declare(strict_types=1);

namespace Mango\Doctrine;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use ErrorException;
use Generator;
use InvalidArgumentException;
use LogicException;
use Mango\Doctrine\Exception\UnexpectedRowsAffected;
use RuntimeException;

use function array_fill;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function strpos;
use function substr;

/**
 * @method $this andWhere($where)
 * @method $this orWhere($where)
 * @method $this setMaxResults(int|null $maxResults)
 * @method array getParameters()
 * @method Type[] getParameterTypes()
 * @method $this set(string $key, string $value)
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

    /** @var string[] */
    private array $quotedTableAliasMap = [];

    private string $lastTableAlias;

    private string $fromAlias;

    private Table $insertInto;

    public function __construct(
        private Connection $connection,
        private SchemaProvider $schema,
    ) {
        $this->builder  = $this->connection->createQueryBuilder();
        $this->platform = $this->connection->getDatabasePlatform();
    }

    public function insertToUpdate(array $data = []): self
    {
        $q = $this->schema->createQuery();

        return $q->update($this->insertInto->getName(), $data);
    }

    public function insert(string $into, array $data): self
    {
        $this->insertInto = $table = $this->schema->getTable($into);

        $this->builder->insert($table->getQuotedName($this->platform));

        $values = [];
        foreach ($data as $key => $value) {
            try {
                $column = $table->getColumn($key);
            } catch (SchemaException) {
                // ignore unknown columns
                continue;
            }

            $type = $column->getType();

            $this->builder->createPositionalParameter($value, $type);

            $columnName = $column->getQuotedName($this->platform);
            $valueSql   = $type->convertToDatabaseValueSQL('?', $this->platform);

            $values[$columnName] = $valueSql;
        }

        $this->builder->values($values);

        return $this;
    }

    public function setRawValue(string $columnName, string $sql): self
    {
        $columnName = $this->insertInto->getColumn($columnName)->getQuotedName($this->platform);

        $this->builder->setValue($columnName, $sql);

        return $this;
    }

    public function orderBy(string|array $sort, ?string $order = null): self
    {
        $this->builder->orderBy($this->quoteColumn($sort), $order);

        return $this;
    }

    public function addOrderBy(string|array $sort, ?string $order = null): self
    {
        $this->builder->addOrderBy($this->quoteColumn($sort), $order);

        return $this;
    }

    public function bulkInsert(string $into, array ...$records): int
    {
        if (! isset($records[0])) {
            return 0;
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

        $values = array_map(fn (Type $type) => $type->convertToDatabaseValueSQL('?', $this->platform), $types);
        $types  = array_merge(...array_fill(0, $recordsCount, $types));

        foreach ($records as $record) {
            $params[] = array_values($record);
        }

        $params = array_merge(...$params);

        if (count($params) !== count($types)) {
            throw new InvalidArgumentException('Invalid record values.');
        }

        $tableName = $table->getQuotedName($this->platform);

        $sql = $this->platform instanceof OraclePlatform
            ? $this->getBulkInsertSQLForOracle($tableName, $columns, $values, $recordsCount)
            : $this->getBulkInsertSQL($tableName, $columns, $values, $recordsCount);

        $rowNum = $this->connection->executeStatement($sql, $params, $types);

        if ($rowNum === $recordsCount) {
            return $rowNum;
        }

        throw new RuntimeException(sprintf('Bulk insert failed: row num (%d) !== records count (%d)', $rowNum, $recordsCount));
    }

    private function getBulkInsertSQL(string $tableName, array $columns, array $values, int $recordsCount): string
    {
        $sql  = "INSERT INTO {$tableName} ";
        $sql .= '(' . implode(',', $columns) . ')';
        $sql .= ' VALUES ';

        $values = '(' . implode(',', $values) . ')';

        return $sql . implode(',', array_fill(0, $recordsCount, $values));
    }

    private function getBulkInsertSQLForOracle(string $tableName, array $columns, array $values, int $recordsCount): string
    {
        $sql = 'INSERT ALL ';

        $columns = '(' . implode(',', $columns) . ')';

        $values = '(' . implode(',', $values) . ')';

        $sql .= implode(' ', array_fill(0, $recordsCount, "INTO {$tableName} {$columns} VALUES {$values}"));
        $sql .= ' SELECT 1 FROM dual';

        return $sql;
    }

    private function getResultAlias(): string
    {
        return 'c' . $this->resultAliasCounter++;
    }

    public function selectFrom(string|array $from, ?string ...$selects): self
    {
        return $this->addFrom([$this->builder, 'from'], $from)->select(...$selects);
    }

    private function addSelectTable(string $alias, Table $table): void
    {
        $this->selectTableMap[$alias] = $table;
        $this->lastTableAlias         = $alias;

        $this->quotedTableAliasMap[$alias] = $alias === $table->getName()
            ? $table->getQuotedName($this->platform)
            : $alias;
    }

    public function addInnerJoin(string|array $join, string|Closure|array $on, ?string $fromAlias = null): self
    {
        $this->addJoin('join', $fromAlias, $join, $on);

        return $this;
    }

    public function addLeftJoin(string|array $join, string|Closure|array $on, ?string $fromAlias = null): self
    {
        $this->addJoin('leftJoin', $fromAlias, $join, $on);

        return $this;
    }

    private function addJoin(string|callable $builderCall, ?string $fromAlias, string|array $join, string|Closure|array $on): string
    {
        $fromAlias ??= $this->fromAlias;

        if (is_string($builderCall)) {
            $builderCall = [$this->builder, $builderCall];
        }

        [$joinTable, $joinAlias] = is_string($join) ? [$join, $join] : $join;

        $this->addSelectTable($joinAlias, $table = $this->schema->getTable($joinTable));

        if ($on instanceof Closure) {
            $on = $on($this);
        } elseif (is_array($on)) {
            [$fromColumn, $joinColumn] = $on;

            $on = $this->eq([$fromAlias, $fromColumn], [$joinAlias, $joinColumn]);
        }

        $builderCall(
            $this->quotedTableAliasMap[$fromAlias],
            $table->getQuotedName($this->platform),
            $this->quotedTableAliasMap[$joinAlias],
            $on,
        );

        return $joinAlias;
    }

    public function join(string $fromAlias, string|array $join, string|Closure|array $on, ?string ...$selects): self
    {
        $this->addInnerJoin($join, $on, $fromAlias)->select(...$selects);

        return $this;
    }

    public function joinOn(string|array $joinTable, string $joinColumn, string $fromColumn, ?string ...$selects): self
    {
        return $this->join($this->fromAlias, $joinTable, [$fromColumn, $joinColumn], ...$selects);
    }

    public function leftJoin(string $fromAlias, string|array $join, string|Closure|array $on, ?string ...$selects): self
    {
        $this->addLeftJoin($join, $on, $fromAlias)->select(...$selects);

        return $this;
    }

    public function leftJoinOn(string|array $joinTable, string $joinColumn, string $fromColumn, ?string ...$selects): self
    {
        return $this->leftJoin($this->fromAlias, $joinTable, [$fromColumn, $joinColumn], ...$selects);
    }

    public function update(string|array $from, array $data = []): self
    {
        $this->addFrom([$this->builder, 'update'], $from);

        foreach ($data as $column => $value) {
            $this->builder->set(...$this->bindUpdate($column, $value));
        }

        return $this;
    }

    public function delete(string ...$from): self
    {
        return $this->addFrom([$this->builder, 'delete'], $from);
    }

    public function from(string ...$from): self
    {
        return $this->addFrom([$this->builder, 'from'], $from);
    }

    public function patch(string|array $from, array $data = []): ?self
    {
        foreach ($data as $column => $value) {
            if ($value === null) {
                unset($data[$column]);
            }
        }

        return $data === [] ? null : $this->update($from, $data);
    }

    private function addFrom(callable $builderCall, string|array $from): self
    {
        $from = is_array($from) && 1 === count($from) ? $from[0] : $from;

        [$fromTable, $fromAlias] = is_string($from) ? [$from, null] : $from;

        $table = $this->schema->getTable($fromTable);
        $builderCall($table->getQuotedName($this->platform), $fromAlias);

        $fromAlias ??= $fromTable;

        $this->addSelectTable($fromAlias, $table);

        $this->fromAlias = $fromAlias;

        return $this;
    }

    /** @return Column[] */
    private function getSelectColumns(Table $table, array $selects): Generator
    {
        if ($selects === []) {
            return yield from $table->getColumnsByAlias();
        }

        if ('*' === ($selects[0] ?? null)) {
            foreach ($table->getColumnsByAlias() as $columnAlias => $column) {
                yield ($selects[$columnAlias] ?? $columnAlias) => $column;
            }

            return;
        }

        foreach ($selects as $alias => $name) {
            yield $alias => $table->getColumn($name);
        }
    }

    public function select(?string ...$selects): self
    {
        $tableAlias = $this->lastTableAlias;

        if (array_key_exists(0, $selects) && $selects[0] === null) {
            return $this;
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
                    $this->quotedTableAliasMap[$tableAlias] . '.' . $column->getQuotedName($this->platform),
                    $this->platform,
                )
                . ' ' . $this->platform->quoteSingleIdentifier($resultAlias);
        }

        return $this;
    }

    public function selectRaw(array $alias, string $type, string|array ...$parts): self
    {
        $sql = '';

        foreach ($parts as $part) {
            if (is_array($part)) {
                [$tableAlias, $column] = $part;

                $column = $this->selectTableMap[$tableAlias]->getColumn($column);

                $sql .= $this->quotedTableAliasMap[$tableAlias] . '.' . $column->getQuotedName($this->platform);
            } else {
                $sql .= $part;
            }
        }

        [$tableAlias, $alias] = $alias;

        $resultAlias = $this->getResultAlias();
        $type        = Type::getType($type);

        $this->resultTypeMap[$resultAlias]        = $type;
        $this->resultTableAliasMap[$resultAlias]  = $tableAlias;
        $this->resultColumnAliasMap[$resultAlias] = $alias;

        $this->selects[] =
            $type->convertToPHPValueSQL($sql, $this->platform) . ' '
            . $this->platform->quoteSingleIdentifier($resultAlias);

        return $this;
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

    public function queryWithWriteLock(): self
    {
        $this->result = $this->connection->executeQuery(
            $this->getSQL() . ' ' . $this->connection->getDatabasePlatform()->getWriteLockSQL(),
            $this->getParameters(),
            $this->getParameterTypes(),
        );

        return $this;
    }

    public function returning(?string ...$selects): self
    {
        $selectSql = $this->schema->createQuery()
            ->from($this->selectTableMap[$this->fromAlias]->getName())
            ->select(...$selects)
            ->getSQL();

        // strlen('SELECT ') === 7
        $returningSql = ' RETURNING ' . substr($selectSql, 7, strpos($selectSql, 'FROM') - 8);

        $this->result = $this->connection->executeQuery(
            $this->getSQL() . $returningSql,
            $this->getParameters(),
            $this->getParameterTypes(),
        );

        return $this;
    }

    public function getSQL(): string
    {
        if ($this->selects !== []) {
            $this->builder->select(...$this->selects);
        }

        return $this->builder->getSQL();
    }

    public function appendParameters(array $params, array $types): self
    {
        foreach ($params as $key => $value) {
            $type = $types[$key] ?? ParameterType::STRING;

            if (is_int($key)) {
                $this->builder->createPositionalParameter($value, $type);
            } elseif (is_string($key)) {
                $this->builder->setParameter($key, $value, $type);
            }
        }

        return $this;
    }

    public function appendQueryParameters(Query|QueryBuilder $query): self
    {
        return $this->appendParameters($query->getParameters(), $query->getParameterTypes());
    }

    private function convertResultToPHPValue(string $resultAlias, mixed $value)
    {
        return $this->resultTypeMap[$resultAlias]
            ->convertToPHPValue($value, $this->platform);
    }

    private function convertResultRow(array $row): array
    {
        $results = [];
        foreach ($row as $resultAlias => $value) {
            $value = $this->convertResultToPHPValue($resultAlias, $value);

            $tableAlias  = $this->resultTableAliasMap[$resultAlias];
            $columnAlias = $this->resultColumnAliasMap[$resultAlias];

            $results[$tableAlias][$columnAlias] = $value;
        }

        return $results;
    }

    private function convertResultRowFlat(array $row): array
    {
        $results = [];
        foreach ($row as $resultAlias => $value) {
            $value = $this->convertResultToPHPValue($resultAlias, $value);

            $columnAlias = $this->resultColumnAliasMap[$resultAlias];

            $results[$columnAlias] = $value;
        }

        return $results;
    }

    public function fetchAssociative(): array|false
    {
        if (false !== $row = $this->getQueryResult()->fetchAssociative()) {
            return $this->convertResultRow($row);
        }

        return $row;
    }

    public function fetchAssociativeFlat(): array|false
    {
        if (false !== $row = $this->getQueryResult()->fetchAssociative()) {
            return $this->convertResultRowFlat($row);
        }

        return $row;
    }

    public function fetchAllAssociative(): array
    {
        $rows = $this->getQueryResult()->fetchAllAssociative();

        foreach ($rows as $i => $row) {
            $rows[$i] = $this->convertResultRow($row);
        }

        return $rows;
    }

    public function fetchAllAssociativeFlat(): array
    {
        $rows = $this->getQueryResult()->fetchAllAssociative();

        foreach ($rows as $i => $row) {
            $rows[$i] = $this->convertResultRowFlat($row);
        }

        return $rows;
    }

    public function fetchAllKeyValue(): array
    {
        $this->ensureHasKeyValue();

        $rows = $this->getQueryResult()->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $key   = $this->convertResultToPHPValue('c0', $row['c0']);
            $value = $this->convertResultToPHPValue('c1', $row['c1']);

            $results[$key] = $value;
        }

        return $results;
    }

    private function ensureHasKeyValue(): void
    {
        if ($this->resultAliasCounter < 2) {
            throw new LogicException(sprintf(
                'Requires the result to contain at least 2 columns, %d given.',
                $this->resultAliasCounter,
            ));
        }
    }

    public function fetchAllAssociativeIndexed(): array
    {
        $this->ensureHasKeyValue();

        $rows = $this->getQueryResult()->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $key = $this->convertResultToPHPValue('c0', $row['c0']);
            unset($row['c0']);

            $results[$key] = $this->convertResultRowFlat($row);
        }

        return $results;
    }

    public function fetchAllAssociativeGrouped(): array
    {
        $this->ensureHasKeyValue();

        $rows = $this->getQueryResult()->fetchAllAssociative();

        $keys    = [];
        $results = [];
        foreach ($rows as $row) {
            $key = $keys[$row['c0']] ??= $this->convertResultToPHPValue('c0', $row['c0']);
            unset($row['c0']);

            $results[$key][] = $this->convertResultRowFlat($row);
        }

        return $results;
    }

    public function fetchColumnGrouped(): array
    {
        $this->ensureHasKeyValue();

        $rows = $this->getQueryResult()->fetchAllAssociative();

        $keys    = [];
        $results = [];
        foreach ($rows as $row) {
            $key = $keys[$row['c0']] ??= $this->convertResultToPHPValue('c0', $row['c0']);

            $results[$key][] = $this->convertResultToPHPValue('c1', $row['c1']);
        }

        return $results;
    }

    public function fetchFirstColumn(): array
    {
        return array_map(
            fn ($value) => $this->convertResultToPHPValue('c0', $value),
            $this->getQueryResult()->fetchFirstColumn(),
        );
    }

    public function fetchOne(mixed $default = false): mixed
    {
        if (false === $values = $this->getQueryResult()->fetchNumeric()) {
            return $default;
        }

        return $this->convertResultToPHPValue('c0', $values[0]);
    }

    public function getBuilder(): QueryBuilder
    {
        return $this->builder;
    }

    public function comparison(string|array $x, string $operator, $y): string
    {
        if (is_array($y)) {
            return implode(' ' . $operator . ' ', [
                $this->quoteColumn($x),
                $this->quoteColumn($y),
            ]);
        }

        [$tableAlias, $column] = $this->splitColumn($x);

        return implode(' ' . $operator . ' ', $this->bind($tableAlias, $column, $y));
    }

    public function assertion(string|array $x, string $operator): string
    {
        [$tableAlias, $column] = $this->splitColumn($x);

        $column = $this->selectTableMap[$tableAlias]->getColumn($column);

        return $this->quotedTableAliasMap[$tableAlias] . '.' . $column->getQuotedName($this->platform)
            . ' ' . $operator;
    }

    public function subQuery(string|array $x, self $subQuery, string $format = '%s'): string
    {
        [$tableAlias, $column] = $this->splitColumn($x);

        $column = $this->selectTableMap[$tableAlias]->getColumn($column);

        $values = $subQuery->builder->getParameters();
        $types  = $subQuery->builder->getParameterTypes();

        foreach ($values as $i => $value) {
            $this->builder->createPositionalParameter($value, $types[$i] ?? null);
        }

        return $this->quotedTableAliasMap[$tableAlias] . '.' . $column->getQuotedName($this->platform)
            . ' ' . sprintf($format, $subQuery->getSQL());
    }

    public function comparisonArray(string|array $x, string $operator, array $y): string
    {
        [$tableAlias, $column] = $this->splitColumn($x);

        $column   = $this->selectTableMap[$tableAlias]->getColumn($column);
        $type     = $column->getType();
        $valueSql = $type->convertToDatabaseValueSQL('?', $this->platform);

        $values = [];
        foreach ($y as $value) {
            $this->builder->createPositionalParameter($value, $type);
            $values[] = $valueSql;
        }

        return implode(' ', [
            $this->quotedTableAliasMap[$tableAlias] . '.' . $column->getQuotedName($this->platform),
            $operator,
            '(' . implode(',', $values) . ')',
        ]);
    }

    public function in(string|array $x, array $y): string
    {
        return $this->comparisonArray($x, 'IN', $y);
    }

    public function notIn(string|array $x, array $y): string
    {
        return $this->comparisonArray($x, 'NOT IN', $y);
    }

    private function splitColumn(string|array $column): array
    {
        if (is_array($column)) {
            return $column;
        }

        if (false === strpos($column, '.')) {
            return [$this->lastTableAlias, $column];
        }

        try {
            return explode('.', $column, 2);
        } catch (ErrorException) {
            throw new InvalidArgumentException(
                sprintf('Given "%s" while expecting "<tableAlias>.<column>".', $column),
            );
        }
    }

    public function quoteColumn(string|array $column): string
    {
        if (is_string($column) && isset($this->insertInto)) {
            return $this->insertInto->getColumn($column)->getQuotedName($this->platform);
        }

        [$tableAlias, $column] = $this->splitColumn($column);

        $column = $this->selectTableMap[$tableAlias]->getColumn($column);

        return $this->quotedTableAliasMap[$tableAlias] . '.' . $column->getQuotedName($this->platform);
    }

    public function bind(string $tableAlias, string $column, $value): array
    {
        $column = $this->selectTableMap[$tableAlias]->getColumn($column);
        $type   = $column->getType();

        $this->builder->createPositionalParameter($value, $type);

        return [
            $this->quotedTableAliasMap[$tableAlias] . '.' . $column->getQuotedName($this->platform),
            $type->convertToDatabaseValueSQL('?', $this->platform),
        ];
    }

    public function bindUpdate(string $column, $value): array
    {
        $column = $this->selectTableMap[$this->fromAlias]->getColumn($column);
        $type   = $column->getType();

        $this->builder->createPositionalParameter($value, $type);

        return [
            $column->getQuotedName($this->platform),
            $type->convertToDatabaseValueSQL('?', $this->platform),
        ];
    }

    public function isNull(string|array $x): string
    {
        return $this->assertion($x, 'IS NULL');
    }

    public function isNotNull(string|array $x): string
    {
        return $this->assertion($x, 'IS NOT NULL');
    }

    public function eq(string|array $x, $y): string
    {
        return $this->comparison($x, '=', $y);
    }

    public function neq(string|array $x, $y): string
    {
        return $this->comparison($x, '<>', $y);
    }

    public function lt(string|array $x, $y): string
    {
        return $this->comparison($x, '<', $y);
    }

    public function lte(string|array $x, $y): string
    {
        return $this->comparison($x, '<=', $y);
    }

    public function gt(string|array $x, $y): string
    {
        return $this->comparison($x, '>', $y);
    }

    public function gte(string|array $x, $y): string
    {
        return $this->comparison($x, '>=', $y);
    }

    public function executeStatement(?int $rowsAffected = null, ?callable $preprocess = null): int
    {
        $sql    = $this->getSQL();
        $params = $this->getParameters();
        $types  = $this->getParameterTypes();

        if ($preprocess) {
            $preprocess($sql, $params, $types);
        }

        $actual = $this->connection->executeStatement($sql, $params, $types);

        if ($actual !== ($rowsAffected ?? $actual)) {
            throw UnexpectedRowsAffected::create($rowsAffected, $actual);
        }

        return $actual;
    }

    public function __call($name, $arguments)
    {
        $returnValue = $this->builder->{$name}(...$arguments);

        return $returnValue === $this->builder ? $this : $returnValue;
    }

    public function and(string|CompositeExpression ...$expressions): CompositeExpression
    {
        return CompositeExpression::and(...$expressions);
    }

    public function or(string|CompositeExpression ...$expressions): CompositeExpression
    {
        return CompositeExpression::or(...$expressions);
    }

    public function where(mixed ...$expressions): self
    {
        $where = [];
        foreach ($expressions as $matchColumn => $expression) {
            if ($expression === null) {
                continue;
            }

            if (is_string($matchColumn)) {
                $expression = is_array($expression) ? $this->in($matchColumn, $expression) : $this->eq($matchColumn, $expression);
            }

            $where[] = $expression;
        }

        if ($where !== []) {
            $this->builder->andWhere(...$where);
        }

        return $this;
    }

    public function andWhere(mixed ...$expressions): self
    {
        return $this->where(...$expressions);
    }

    public function onConflictDoNothing(string|array $conflict, ?int $rowsAffected = null): int
    {
        $conflict = array_map(fn ($n) => $this->quoteColumn($n), (array) $conflict);

        return $this->executeStatement($rowsAffected, static function (&$sql) use ($conflict) {
            $sql .= ' ON CONFLICT (' . implode(', ', $conflict) . ') DO NOTHING';
        });
    }
}
