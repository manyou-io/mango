<?php

declare(strict_types=1);

namespace Mango\Doctrine\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Type;

class TinyIntType extends Type
{
    public const NAME = 'tinyint';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof AbstractMySQLPlatform => 'TINYINT' . (empty($column['unsigned']) ? '' : ' UNSIGNED'),
            $platform instanceof SQLServerPlatform => 'TINYINT',
            $platform instanceof OraclePlatform => 'NUMBER(3)',
            default => $platform->getSmallIntTypeDeclarationSQL($column),
        };
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return $value === null ? null : (int) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
