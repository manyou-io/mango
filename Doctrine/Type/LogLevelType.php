<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Monolog\Level;

use function is_string;

class LogLevelType extends TinyIntEnumType
{
    use BackedTinyIntEnum {
        valueToId as enumToId;
    }

    public const NAME = 'log_level';

    public function getName(): string
    {
        return self::NAME;
    }

    private function getEnumClass(): string
    {
        return Level::class;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : Level::from((int) $value)->toPsrLogLevel();
    }

    public function idToValue(int $id): ?string
    {
        $value = $this->getValueMap()[$id] ?? null;

        return $value === null ? null : Level::from($value)->toPsrLogLevel();
    }

    public function valueToId($value): ?int
    {
        if (is_string($value)) {
            return $this->getIdMap()[Level::fromName($value)->value];
        }

        return $this->enumToId($value);
    }
}
