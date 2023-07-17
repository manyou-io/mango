<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Doctrine\Table;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;
use Manyou\Mango\Doctrine\Type\UsDateTimeImmutableType;

class ScheduledMessagesTable implements TableProvider
{
    public const NAME = 'scheduled_messages';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('key', Types::STRING);
        $table->addColumn('envelope', Types::JSON)->setPlatformOptions(['jsonb' => true]);
        $table->addColumn('available_at', Types::DATETIME_IMMUTABLE, alias: 'availableAt');
        $table->addColumn('last_dispatched_at', UsDateTimeImmutableType::NAME, alias: 'lastDispatchedAt');
        $table->setPrimaryKey(['key']);
        $table->addIndex(['available_at']);

        return $table;
    }
}
