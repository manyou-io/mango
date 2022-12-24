<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use BackedEnum;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Stringable;

use function is_a;

trait BackedEnumType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    abstract private function getEnumClass(): string;

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_a($value, $this->getEnumClass())) {
            if ($value instanceof Stringable) {
                $value = (string) $value;
            }

            $value = $this->getEnumClass()::from($value);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        throw ConversionException::conversionFailedInvalidType($value, static::class, ['null', BackedEnum::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return $value === null ? null : $this->getEnumClass()::from($value);
    }
}
