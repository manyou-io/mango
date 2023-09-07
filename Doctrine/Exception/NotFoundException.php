<?php

declare(strict_types=1);

namespace Mango\Doctrine\Exception;

use RuntimeException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
class NotFoundException extends RuntimeException implements ExceptionInterface
{
    public static function create()
    {
        return new self('Not Found');
    }
}
