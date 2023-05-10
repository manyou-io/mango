<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Exception;

use RuntimeException;

class RecordsNotFound extends RuntimeException
{
    public static function create()
    {
        return new self('Records not found.');
    }
}
