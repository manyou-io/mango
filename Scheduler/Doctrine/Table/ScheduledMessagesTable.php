<?php

declare(strict_types=1);

namespace Mango\Scheduler\Doctrine\Table;

use Doctrine\DBAL\Types\Types;
use Mango\Doctrine\Schema\TableBuilder;
use Mango\Doctrine\Table;

class ScheduledMessagesTable implements TableBuilder
{
    public const NAME = 'scheduled_messages';

    public function getName(): string
    {
        return self::NAME;
    }

    public function build(Table $table): void
    {
        $table->addColumn('key', Types::STRING);
        $table->addColumn('message_id', Types::BIGINT);
        $table->setPrimaryKey(['key']);
        $table->addUniqueIndex(['message_id']);
    }
}
