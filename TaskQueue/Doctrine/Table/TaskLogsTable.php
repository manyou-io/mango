<?php

declare(strict_types=1);

namespace Mango\TaskQueue\Doctrine\Table;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Mango\Doctrine\Schema\TableBuilder;
use Mango\Doctrine\Table;
use Mango\Doctrine\Type\LogLevelType;

class TaskLogsTable implements TableBuilder
{
    public const NAME = 'task_logs';

    public function getName(): string
    {
        return self::NAME;
    }

    public function build(Table $table): void
    {
        $table->addColumn('id', 'ulid');
        $table->addColumn('task_id', 'ulid');
        $table->addColumn('level', LogLevelType::NAME);
        $table->addColumn('message', Types::TEXT);
        $table->addColumn('context', Types::JSON)->setPlatformOptions(['jsonb' => true]);
        $table->addColumn('extra', Types::JSON)->setPlatformOptions(['jsonb' => true]);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint(TasksTable::NAME, ['task_id'], ['id']);
    }
}
