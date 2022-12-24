<?php

declare(strict_types=1);

namespace Manyou\Mango\Operation\Doctrine\Type;

use Doctrine\DBAL\Types\Type;
use Manyou\Mango\Doctrine\Type\PhpEnumType;
use Manyou\Mango\Operation\Enum\OperationStatus;

class OperationStatusType extends Type
{
    use PhpEnumType;

    public const NAME = 'operation_status';

    public function getName(): string
    {
        return self::NAME;
    }

    private function getEnumClass(): string
    {
        return OperationStatus::class;
    }
}
