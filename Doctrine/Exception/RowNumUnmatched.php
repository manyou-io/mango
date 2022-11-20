<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Exception;

use RuntimeException;

use function sprintf;

class RowNumUnmatched extends RuntimeException
{
    public static function create(int $expectedRowNum, int $rowNum)
    {
        return new self(sprintf('Changed row numbers unmatched: (expected) %d !== (actual) %s', $expectedRowNum, $rowNum));
    }
}
