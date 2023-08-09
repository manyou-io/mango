<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Messenger;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

class ScheduleMessageStamp implements NonSendableStampInterface
{
    private function __construct(
        public readonly string $key,
        public readonly string $action,
    ) {
    }

    public static function insert(string $key): self
    {
        return new self($key, 'insert');
    }

    public static function update(string $key): self
    {
        return new self($key, 'update');
    }

    public static function upsert(string $key): self
    {
        return new self($key, 'upsert');
    }

    public function isUpdate(): bool
    {
        return 'update' === $this->action;
    }

    public function isUpsert(): bool
    {
        return 'upsert' === $this->action;
    }

    public function isInsert(): bool
    {
        return 'insert' === $this->action;
    }
}
