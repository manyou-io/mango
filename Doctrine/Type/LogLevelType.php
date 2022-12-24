<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Monolog\Level;
use Psr\Log\LogLevel;

class LogLevelType extends Type
{
    use EnumType {
        convertToDatabaseValue as doConvertToDatabaseValue;
    }

    public const NAME = 'log_level';

    public function getName(): string
    {
        return self::NAME;
    }

    private function getEnums(): array
    {
        return [
            LogLevel::DEBUG,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING,
            LogLevel::ERROR,
            LogLevel::CRITICAL,
            LogLevel::ALERT,
            LogLevel::EMERGENCY,
        ];
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): int|string|null
    {
        if ($value instanceof Level) {
            $value = $value->toPsrLogLevel();
        }

        return $this->doConvertToDatabaseValue($value, $platform);
    }
}
