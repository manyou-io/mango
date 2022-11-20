<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Stringable;

use function array_map;
use function is_a;

trait BackedTinyIntEnum
{
    use ArrayTinyIntEnum;

    abstract private function getEnumClass(): string;

    private function getEnums(): array
    {
        return array_map(static fn (BackedEnum $enum) => $enum->value, $this->getEnumClass()::cases());
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?BackedEnum
    {
        return $value === null ? null : $this->getEnumClass()::from($value);
    }

    public function valueToId($value): ?int
    {
        if (! is_a($value, $this->getEnumClass())) {
            if ($value instanceof Stringable) {
                $value = (string) $value;
            }

            $value = $this->getEnumClass()::from($value);
        }

        return $this->getIdMap()[$value->value] ?? null;
    }

    public function idToValue(int $id): ?BackedEnum
    {
        $value = $this->getValueMap()[$id] ?? null;

        return $value === null ? null : $this->getEnumClass()::from($value);
    }
}
