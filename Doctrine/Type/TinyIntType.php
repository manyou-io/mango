<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

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
        if ($platform instanceof AbstractMySQLPlatform) {
            return 'TINYINT' . (empty($column['unsigned']) ? '' : ' UNSIGNED');
        }

        if ($platform instanceof SQLServerPlatform) {
            return 'TINYINT';
        }

        if ($platform instanceof OraclePlatform) {
            return 'NUMBER(3)';
        }

        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function getBindingType(): int
    {
        return ParameterType::INTEGER;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return $value === null ? null : (int) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
