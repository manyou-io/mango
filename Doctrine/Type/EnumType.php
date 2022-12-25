<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

use function array_map;
use function implode;

trait EnumType
{
    use TinyIntArrayEnum {
        convertToPHPValueSQL as caseWhenSQL;
        convertToDatabaseValue as convertValueToId;
    }

    public function convertToPHPValueSQL($sqlExpr, $platform): string
    {
        return $this->usingTinyInt($platform) ? $this->caseWhenSQL($sqlExpr, $platform) : $sqlExpr;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): int|string|null
    {
        $id = $this->convertValueToId($value, $platform);

        return $this->usingTinyInt($platform) ? $id : $value;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return match (true) {
            // For Postgres: CREATE THE TYPE MANUALLY!
            $platform instanceof PostgreSQLPlatform => 'TEXT',
            $platform instanceof AbstractMySQLPlatform,
            $platform instanceof OraclePlatform => $this->getEnumSQLDeclaration($this->getEnums(), $platform),
            default => $this->getTinyIntSQLDeclaration($platform),
        };
    }

    private function usingTinyInt(AbstractPlatform $platform)
    {
        return match (true) {
            $platform instanceof PostgreSQLPlatform,
            $platform instanceof AbstractMySQLPlatform,
            $platform instanceof OraclePlatform => false,
            default => true,
        };
    }

    private function getTinyIntSQLDeclaration(AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof SQLServerPlatform => 'TINYINT',
            $platform instanceof SqlitePlatform => 'INTEGER',
            default => $platform->getSmallIntTypeDeclarationSQL(['unsigned' => true]),
        };
    }

    private function getEnumSQLDeclaration(array $values, AbstractPlatform $platform): string
    {
        return 'ENUM('
            . implode(', ', array_map(static fn (string $value) => $platform->quoteStringLiteral($value), $values))
            . ')';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
