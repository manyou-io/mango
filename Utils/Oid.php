<?php

declare(strict_types=1);

namespace Mango\Utils;

use function base_convert;
use function bin2hex;
use function floor;
use function microtime;
use function random_bytes;
use function random_int;
use function sprintf;
use function strtr;
use function substr;

class Oid
{
    private static int $startEpoch = 1676010095749;

    public static function create()
    {
        $time = ((int) floor(microtime(true) * 1000)) - self::$startEpoch;

        return self::toCrockfordBase32B(sprintf('%010x%02x%03x', $time, self::nextProcessSeq(), self::nextRandomSeq()));
    }

    public static function toCrockfordBase32B(string $hex): string
    {
        $base32 = sprintf(
            '%04s%04s%04s',
            base_convert(substr($hex, 0, 5), 16, 32),
            base_convert(substr($hex, 5, 5), 16, 32),
            base_convert(substr($hex, 10, 5), 16, 32),
        );

        return strtr($base32, 'abcdefghijklmnopqrstuv', 'ABCDEFGHJKMNPQRSTVWXYZ');
    }

    public static function password(): string
    {
        $hex = bin2hex(random_bytes(15));

        $base32 = sprintf(
            '%04s.%04s.%04s',
            base_convert(substr($hex, 0, 5), 16, 32),
            base_convert(substr($hex, 5, 5), 16, 32),
            base_convert(substr($hex, 10, 5), 16, 32),
        );

        return strtr($base32, 'abcdefghijklmnopqrstuv', 'abcdefghjkmnpqrstvwxyz');
    }

    private static ?int $processSeq = null;

    private static function nextProcessSeq(): int
    {
        if (self::$processSeq === null) {
            return self::$processSeq = random_int(0, 255);
        }

        if (255 >= $processSeq = random_int(self::$processSeq, 383)) {
            return self::$processSeq = $processSeq;
        }

        self::$processSeq = null;

        return self::nextProcessSeq();
    }

    private static ?int $randomSeq = null;

    private static function nextRandomSeq(): int
    {
        if (self::$randomSeq === null) {
            return self::$randomSeq = random_int(0, 4095);
        }

        if (4095 >= $randomSeq = random_int(self::$randomSeq, 5119)) {
            return self::$randomSeq = $randomSeq;
        }

        self::$randomSeq = null;

        return self::nextRandomSeq();
    }
}
