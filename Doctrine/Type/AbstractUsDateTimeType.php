<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use DateTimeInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

use function is_a;

abstract class AbstractUsDateTimeType extends Type
{
    private const FORMATS = [AbstractMySQLPlatform::class => 'Y-m-d H:i:s.u'];

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($platform instanceof AbstractMySQLPlatform) {
            return 'DATETIME(6)';
        }

        throw Exception::notSupported(__METHOD__);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return $value;
        }

        if (! $platform instanceof AbstractMySQLPlatform) {
            throw Exception::notSupported(__METHOD__);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(self::FORMATS[AbstractMySQLPlatform::class]);
        }

        throw ConversionException::conversionFailedInvalidType($value, static::class, ['null', 'DateTime']);
    }

    abstract protected function getClassName(): string;

    abstract protected function createDateFromFormat(string $format, string $datetime): mixed;

    abstract protected function createDate(string $datetime): mixed;

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null || is_a($value, $this->getClassName())) {
            return $value;
        }

        if (! $platform instanceof AbstractMySQLPlatform) {
            throw Exception::notSupported(__METHOD__);
        }

        $format = self::FORMATS[AbstractMySQLPlatform::class];

        $val = $this->createDateFromFormat($format, $value);

        if ($val === false) {
            $val = $this->createDate($value);
        }

        if ($val === false) {
            throw ConversionException::conversionFailedFormat($value, static::class, $format);
        }

        return $val;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
