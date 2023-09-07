<?php

declare(strict_types=1);

namespace Mango\TaskQueue\Doctrine\Table;

use Doctrine\DBAL\Schema\Schema;
use Mango\Doctrine\Schema\TableBuilder;
use Mango\Doctrine\Table;
use Mango\TaskQueue\Doctrine\Type\TaskStatusType;

class TasksTable implements TableBuilder
{
    public const NAME = 'tasks';

    public function getName(): string
    {
        return self::NAME;
    }

    public function build(Table $table): void
    {
        $table->addColumn('id', 'ulid');
        $table->addColumn('status', TaskStatusType::NAME);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['status']);
    }
}
