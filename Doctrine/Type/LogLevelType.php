<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use Monolog\Level;
use Psr\Log\LogLevel;

class LogLevelType extends TinyIntEnumType
{
    use ArrayTinyIntEnum {
        valueToId as traitValueToId;
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

    public function valueToId($value): ?int
    {
        if ($value instanceof Level) {
            $value = $value->toPsrLogLevel();
        }

        return $this->traitValueToId($value);
    }
}
