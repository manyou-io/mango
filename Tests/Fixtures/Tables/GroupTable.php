<?php

declare(strict_types=1);

namespace Mango\Tests\Fixtures\Tables;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Mango\Doctrine\Schema\TableBuilder;
use Mango\Doctrine\Table;

class GroupTable implements TableBuilder
{
    public const NAME = 'group';

    public function getName(): string
    {
        return self::NAME;
    }

    public function build(Table $table): void
    {
        $table->addColumn('id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn(
            'order',
            Types::STRING,
            ['length' => 63],
            'orderString',
        );
        $table->setPrimaryKey(['id']);
    }
}
