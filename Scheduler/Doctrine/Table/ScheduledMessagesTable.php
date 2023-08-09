<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Doctrine\Table;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;

class ScheduledMessagesTable implements TableProvider
{
    public const NAME = 'scheduled_messages';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('key', Types::STRING);
        $table->addColumn('message_id', Types::BIGINT, alias: 'messageId');
        $table->setPrimaryKey(['key']);
        $table->addUniqueIndex(['message_id']);

        return $table;
    }
}
