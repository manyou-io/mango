<?php

declare(strict_types=1);

namespace Mango\Doctrine\Type;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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

    abstract private function getName(): string;

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
            throw new ConversionException(sprintf('Failed to convert value to %s: %s', $className, $e->getMessage()), $e->getCode(), $e);
        }
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?BackedEnum
    {
        if ($value === null) {
            return null;
        }

        return $this->getEnumClass()::from($value);
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        if ($platform instanceof PostgreSQLPlatform) {
            return [$this->getName()];
        }

        return [];
    }
}
