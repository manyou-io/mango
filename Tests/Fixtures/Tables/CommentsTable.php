<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests\Fixtures\Tables;

use Doctrine\DBAL\Schema\Schema;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;

class CommentsTable implements TableProvider
{
    public const NAME = 'comments';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', 'ulid');
        $table->setPrimaryKey(['id']);

        return $table;
    }
}
