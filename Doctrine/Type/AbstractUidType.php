<?php

declare(strict_types=1);

namespace Mango\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use Symfony\Component\Uid\AbstractUid;

use function bin2hex;
use function get_debug_type;
use function is_resource;
use function is_string;
use function sprintf;
use function stream_get_contents;

abstract class AbstractUidType extends Type
{
    /** @return class-string<AbstractUid> */
    abstract protected function getUidClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof PostgreSQLPlatform => 'UUID',
            $platform instanceof MariaDBPlatform => 'UUID',
            default => $platform->getBinaryTypeDeclarationSQL([
                'length' => '16',
                'fixed' => true,
            ]),
        };
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AbstractUid
    {
        if ($value instanceof AbstractUid || null === $value) {
            return $value;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value, -1, 0);
        }

        if (! is_string($value)) {
            throw new ConversionException(sprintf('Expected %s, got %s', $this->getUidClass(), get_debug_type($value)));
        }

        try {
            return $this->getUidClass()::fromString($value);
        } catch (InvalidArgumentException $e) {
            throw new ConversionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $value = $this->convertToAbstractUid($value);

        return match (true) {
            $value === null => null,
            $platform instanceof PostgreSQLPlatform => $value->toRfc4122(),
            $platform instanceof MariaDBPlatform => $value->toRfc4122(),
            $platform instanceof OraclePlatform => bin2hex($value->toBinary()),
            default => $value->toBinary(),
        };
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof OraclePlatform => 'HEXTORAW(' . $sqlExpr . ')',
            default => $sqlExpr,
        };
    }

    private function convertToAbstractUid(mixed $value): ?AbstractUid
    {
        if ($value instanceof AbstractUid) {
            return $value;
        }

        if (null === $value || '' === $value) {
            return null;
        }

        if (is_string($value)) {
            return $this->getUidClass()::fromString($value);
        }

        throw new ConversionException(sprintf('Expected %s, got %s', $this->getUidClass(), get_debug_type($value)));
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
