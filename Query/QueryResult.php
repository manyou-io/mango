<?php

declare(strict_types=1);

namespace Mango\Query;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use LogicException;

use function array_keys;
use function array_map;

class QueryResult
{
    private ?string $key0;

    private ?string $key1;

    /** @param array<string, ResultColumnMetadata> $metadata */
    public function __construct(
        private AbstractPlatform $platform,
        private Result $result,
        private array $metadata,
    ) {
        $keys       = array_keys($metadata);
        $this->key0 = $keys[0] ?? null;
        $this->key1 = $keys[1] ?? null;
    }

    private function requireOneColumn(): void
    {
        if ($this->key0 === null) {
            throw new LogicException('The method requires at least one column in each result.');
        }
    }

    private function requireTwoColumns(): void
    {
        if ($this->key0 === null || $this->key1 === null) {
            throw new LogicException('The method requires at least two columns in each result.');
        }
    }

    private function convertResultToPHPValue(string $key, mixed $value)
    {
        $metadata = $this->metadata[$key];

        return $metadata->type->convertToPHPValue($value, $this->platform);
    }

    private function convertResultRow(array $row): array
    {
        $results = [];
        foreach ($row as $key => $value) {
            $metadata = $this->metadata[$key];
            $value    = $this->convertResultToPHPValue($key, $value);

            $results[$metadata->group][$metadata->name] = $value;
        }

        return $results;
    }

    private function convertResultRowFlat(array $row): array
    {
        $results = [];
        foreach ($row as $key => $value) {
            $metadata = $this->metadata[$key];
            $value    = $this->convertResultToPHPValue($key, $value);

            $results[$metadata->name] = $value;
        }

        return $results;
    }

    public function fetchAssociative(): array|false
    {
        if (false !== $row = $this->result->fetchAssociative()) {
            return $this->convertResultRow($row);
        }

        return $row;
    }

    public function fetchAssociativeFlat(): array|false
    {
        if (false !== $row = $this->result->fetchAssociative()) {
            return $this->convertResultRowFlat($row);
        }

        return $row;
    }

    public function fetchAllAssociative(): array
    {
        $rows = $this->result->fetchAllAssociative();

        foreach ($rows as $i => $row) {
            $rows[$i] = $this->convertResultRow($row);
        }

        return $rows;
    }

    public function fetchAllAssociativeFlat(): array
    {
        $rows = $this->result->fetchAllAssociative();

        foreach ($rows as $i => $row) {
            $rows[$i] = $this->convertResultRowFlat($row);
        }

        return $rows;
    }

    public function fetchAllKeyValue(): array
    {
        $this->requireTwoColumns();

        $rows = $this->result->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $key   = $this->convertResultToPHPValue($this->key0, $row[$this->key0]);
            $value = $this->convertResultToPHPValue($this->key1, $row[$this->key1]);

            $results[$key] = $value;
        }

        return $results;
    }

    public function fetchAllAssociativeIndexed(): array
    {
        $this->requireTwoColumns();

        $rows = $this->result->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $key = $this->convertResultToPHPValue($this->key0, $row[$this->key0]);
            unset($row[$this->key0]);

            $results[$key] = $this->convertResultRowFlat($row);
        }

        return $results;
    }

    public function fetchAllAssociativeGrouped(): array
    {
        $this->requireTwoColumns();

        $rows = $this->result->fetchAllAssociative();

        $keys    = [];
        $results = [];
        foreach ($rows as $row) {
            $key = $keys[$row[$this->key0]] ??= $this->convertResultToPHPValue($this->key0, $row[$this->key0]);
            unset($row[$this->key0]);

            $results[$key][] = $this->convertResultRowFlat($row);
        }

        return $results;
    }

    public function fetchColumnGrouped(): array
    {
        $this->requireTwoColumns();

        $rows = $this->result->fetchAllAssociative();

        $keys    = [];
        $results = [];
        foreach ($rows as $row) {
            $key = $keys[$row[$this->key0]] ??= $this->convertResultToPHPValue($this->key0, $row[$this->key0]);

            $results[$key][] = $this->convertResultToPHPValue($this->key1, $row[$this->key1]);
        }

        return $results;
    }

    public function fetchFirstColumn(): array
    {
        $this->requireOneColumn();

        return array_map(
            fn ($value) => $this->convertResultToPHPValue($this->key0, $value),
            $this->result->fetchFirstColumn(),
        );
    }

    public function fetchOne(mixed $default = false): mixed
    {
        $this->requireOneColumn();

        if (false === $values = $this->result->fetchNumeric()) {
            return $default;
        }

        return $this->convertResultToPHPValue($this->key0, $values[0]);
    }
}
