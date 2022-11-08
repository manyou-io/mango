<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use function array_flip;

trait ArrayTinyIntEnum
{
    abstract private function getEnums(): array;

    private static array $valueMap;

    protected function getValueMap(): array
    {
        if (! isset(self::$valueMap)) {
            self::$valueMap = [null, ...$this->getEnums()];
            unset(self::$valueMap[0]);
        }

        return self::$valueMap;
    }

    private static array $idMap;

    private function getIdMap(): array
    {
        if (! isset(self::$idMap)) {
            self::$idMap = array_flip($this->getValueMap());
        }

        return self::$idMap;
    }

    public function valueToId($value): ?int
    {
        return $this->getIdMap()[$value] ?? null;
    }

    protected function idToValue(int $id): mixed
    {
        return $this->getValueMap()[$id] ?? null;
    }
}
