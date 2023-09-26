<?php

declare(strict_types=1);

namespace Mango\Doctrine\Exception;

use RuntimeException;
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
class NotFound extends RuntimeException implements ExceptionInterface
{
}
