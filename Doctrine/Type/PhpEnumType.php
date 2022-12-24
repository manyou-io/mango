<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use LogicException;
use UnitEnum;

use function array_map;
use function is_a;
use function sprintf;

trait PhpEnumType
{
    use EnumType {
        convertToDatabaseValue as doConvertToDatabaseValue;
    }

    abstract private function getEnumClass(): string;

    private function getEnums(): array
    {
        if (! is_a(UnitEnum::class, $className = $this->getEnumClass())) {
            return new LogicException(sprintf('%s::getEnumClass() should return a class name of enum.', $this::class));
        }

        return array_map(self::getEnumValue(...), $className::cases());
    }

    private static function getEnumValue(UnitEnum $enum): string
    {
        return $enum instanceof BackedEnum ? $enum->value : $enum->name;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): int|string|null
    {
        if (is_a($value, $className = $this->getEnumClass())) {
            $value = self::getEnumValue($value);
        }

        try {
            return $this->doConvertToDatabaseValue($value);
        } catch (ConversionException $e) {
            throw ConversionException::conversionFailedInvalidType($value, $this::class, ['null', $className], $e);
        }
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?UnitEnum
    {
        return $value === null ? null : $this->getEnumClass()::from($value);
    }
}
