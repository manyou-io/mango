<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests\Fixtures\Tables;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;

class GroupTable implements TableProvider
{
    public const NAME = 'group';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn(
            'order',
            Types::STRING,
            ['length' => 63],
            'orderString',
        );
        $table->setPrimaryKey(['id']);

        return $table;
    }
}
