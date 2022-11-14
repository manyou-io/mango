<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests\Fixtures\Tables;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;

class PostsTable implements TableProvider
{
    public const NAME = 'posts';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('group_id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('title', Types::STRING, ['length' => 63]);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint(GroupTable::NAME, ['group_id'], ['id']);

        return $table;
    }
}
