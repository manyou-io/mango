<?php

declare(strict_types=1);

namespace Mango\Tests\Fixtures\Tables;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Mango\Doctrine\Schema\TableBuilder;
use Mango\Doctrine\Table;

class PostsTable implements TableBuilder
{
    public const NAME = 'posts';

    public function getName(): string
    {
        return self::NAME;
    }

    public function build(Table $table): void
    {
        $table->addColumn('id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('group_id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('title', Types::STRING, ['length' => 63]);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint(GroupTable::NAME, ['group_id'], ['id']);
    }
}
