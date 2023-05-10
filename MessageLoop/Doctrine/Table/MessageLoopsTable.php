<?php

declare(strict_types=1);

namespace Manyou\Mango\MessageLoop\Doctrine\Table;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;

class MessageLoopsTable implements TableProvider
{
    public const NAME = 'message_loops';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('key', Types::STRING);
        $table->addColumn('loop_id', 'ulid', alias: 'loopId');

        return $table;
    }
}
