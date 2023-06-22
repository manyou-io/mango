<?php

declare(strict_types=1);

namespace Manyou\Mango\TaskQueue\Doctrine\Table;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;
use Manyou\Mango\Doctrine\Type\LogLevelType;
use Manyou\Mango\Doctrine\Type\ObjectJsonType;

class TaskLogsTable implements TableProvider
{
    public const NAME = 'task_logs';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', 'ulid');
        $table->addColumn('task_id', 'ulid');
        $table->addColumn('level', LogLevelType::NAME);
        $table->addColumn('message', Types::TEXT);
        $table->addColumn('context', ObjectJsonType::NAME);
        $table->addColumn('extra', ObjectJsonType::NAME);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint(TasksTable::NAME, ['task_id'], ['id']);

        return $table;
    }
}
