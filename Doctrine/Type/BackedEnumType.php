<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use LogicException;

use function array_map;
use function is_a;
use function sprintf;

trait BackedEnumType
{
    use EnumType {
        convertToDatabaseValue as doConvertToDatabaseValue;
    }

    abstract private function getEnumClass(): string;

    private function getEnums(): array
    {
        if (! is_a($className = $this->getEnumClass(), BackedEnum::class, true)) {
            throw new LogicException(sprintf('%s::getEnumClass() should return a class name of backed enum.', $this::class));
        }

        return array_map(self::getEnumValue(...), $className::cases());
    }

    private static function getEnumValue(BackedEnum $enum): string
    {
        return $enum->value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): int|string|null
    {
        if (is_a($value, $className = $this->getEnumClass(), false)) {
            $value = self::getEnumValue($value);
        }

        try {
            return $this->doConvertToDatabaseValue($value, $platform);
        } catch (ConversionException $e) {
            throw ConversionException::conversionFailedInvalidType($value, $this::class, ['null', $className], $e);
        }
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?BackedEnum
    {
        if ($value === null) {
            return null;
        }

        return $this->getEnumClass()::from($value);
    }
}
