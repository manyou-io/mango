<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Doctrine\Table;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;

class SchedulerTriggersTable implements TableProvider
{
    public const NAME = 'scheduler_triggers';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('delay_until', Types::DATETIME_IMMUTABLE, alias: 'delayUntil');
        $table->setPrimaryKey(['delay_until']);

        return $table;
    }
}
