<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Contract;

interface PolymorphicSubType
{
    public function getTableName(): string;
}
