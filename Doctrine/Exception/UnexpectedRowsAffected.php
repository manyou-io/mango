<?php

declare(strict_types=1);

namespace Mango\Doctrine\Exception;

use RuntimeException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

use function sprintf;

#[WithHttpStatus(409)]
class UnexpectedRowsAffected extends RuntimeException implements ExceptionInterface
{
    public static function create(int $expected, int $actual)
    {
        return new self(sprintf('Unexpected number of rows affected: actual %d; expected %d.', $actual, $expected));
    }
}
