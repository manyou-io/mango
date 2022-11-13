<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;

use function is_int;
use function sprintf;

abstract class TinyIntEnumType extends TinyIntType
{
    public const DEFAULT_OPTIONS = ['unsigned' => true];

    abstract protected function valueToId($value): ?int;

    abstract protected function getValueMap(): array;

    public function convertToPHPValueSQL($sqlExpr, $platform): string
    {
        $sql = 'CASE ';

        foreach ($this->getValueMap() as $id => $value) {
            $value = is_int($value) ? $value : $platform->quoteStringLiteral($value);
            $sql  .= sprintf('WHEN %s = %s THEN %s ', $sqlExpr, $id, $value);
        }

        $sql .= 'END';

        return $sql;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (null !== $id = $this->valueToId($value)) {
            return $id;
        }

        throw new ConversionException(sprintf("Could not convert PHP value '%s' of Doctrine Type %s to database value", $value, $this->getName()));
    }
}
