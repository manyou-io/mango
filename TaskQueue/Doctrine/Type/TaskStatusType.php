<?php

declare(strict_types=1);

namespace Manyou\Mango\TaskQueue\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Manyou\Mango\Doctrine\Type\BackedEnumType;
use Manyou\Mango\TaskQueue\Enum\TaskStatus;

class TaskStatusType extends Type
{
    use BackedEnumType;

    public const NAME = 'task_status';

    public function getName(): string
    {
        return self::NAME;
    }

    private function getEnumClass(): string
    {
        return TaskStatus::class;
    }

    private function usingTinyInt(AbstractPlatform $platform): bool
    {
        return true;
    }
}
