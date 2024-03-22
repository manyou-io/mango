<?php

declare(strict_types=1);

namespace Mango\Doctrine\Type;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

use function get_debug_type;
use function is_a;
use function sprintf;

abstract class AbstractUsDateTimeType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof AbstractMySQLPlatform => 'DATETIME(6)',
            $platform instanceof PostgreSQLPlatform => 'timestamp',
            default => throw new Exception('Platform not supported'),
        };
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            $value = DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone('UTC'));

            return $value->format($this->getFormat($platform));
        }

        throw new ConversionException(sprintf('Expected %s, got %s', DateTimeInterface::class, get_debug_type($value)));
    }

    private function getFormat(AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof AbstractMySQLPlatform => 'Y-m-d H:i:s.u',
            $platform instanceof PostgreSQLPlatform => 'Y-m-d H:i:s.u',
            default => throw new Exception('Platform not supported'),
        };
    }

    abstract protected function getClassName(): string;

    abstract protected function createDateFromFormat(string $format, string $datetime): mixed;

    abstract protected function createDate(string $datetime): mixed;

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null || is_a($value, $this->getClassName())) {
            return $value;
        }

        $format = $this->getFormat($platform);

        $val = $this->createDateFromFormat($format, $value);

        if ($val === false) {
            $val = $this->createDate($value);
        }

        if ($val === false) {
            throw new ConversionException('Failed to convert value to ' . $this->getClassName());
        }

        return $val;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
