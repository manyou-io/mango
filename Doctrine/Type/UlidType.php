<?php

declare(strict_types=1);

namespace Mango\Doctrine\Type;

use Symfony\Component\Uid\Ulid;

final class UlidType extends AbstractUidType
{
    public function getName(): string
    {
        return 'ulid';
    }

    protected function getUidClass(): string
    {
        return Ulid::class;
    }
}
