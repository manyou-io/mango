<?php

declare(strict_types=1);

namespace Manyou\Mango\TaskQueue\Doctrine\Table;

use Doctrine\DBAL\Schema\Schema;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;
use Manyou\Mango\TaskQueue\Doctrine\Type\TaskStatusType;

class TasksTable implements TableProvider
{
    public const NAME = 'tasks';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', 'ulid');
        $table->addColumn('status', TaskStatusType::NAME);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['status']);

        return $table;
    }
}
