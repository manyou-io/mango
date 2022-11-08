<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Type;

use Symfony\Component\Uid\Uuid;

final class UuidType extends AbstractUidType
{
    public function getName(): string
    {
        return 'uuid';
    }

    protected function getUidClass(): string
    {
        return Uuid::class;
    }
}
