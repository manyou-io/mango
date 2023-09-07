<?php

declare(strict_types=1);

namespace Mango\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;

use function sprintf;

trait TinyIntMapping
{
    abstract private function valueToId(string $value): ?int;

    abstract private function getValueMap(): array;

    public function convertToPHPValueSQL($sqlExpr, $platform): string
    {
        $sql = 'CASE ';

        foreach ($this->getValueMap() as $id => $value) {
            $value = $platform->quoteStringLiteral($value);
            $sql  .= sprintf('WHEN %s = %s THEN %s ', $sqlExpr, $id, $value);
        }

        $sql .= 'END';

        return $sql;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if (null !== $id = $this->valueToId($value)) {
            return $id;
        }

        throw new ConversionException(sprintf("Could not convert PHP value '%s' of Doctrine Type %s to database value", $value, $this::class));
    }
}
