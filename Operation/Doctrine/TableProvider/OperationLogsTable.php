<?php

declare(strict_types=1);

namespace Manyou\Mango\Operation\Doctrine\TableProvider;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;
use Manyou\Mango\Doctrine\Type\LogLevelType;

class OperationLogsTable implements TableProvider
{
    public const NAME = 'operation_logs';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', 'ulid');
        $table->addColumn('operation_id', 'ulid');
        $table->addColumn('level', LogLevelType::NAME);
        $table->addColumn('message', Types::TEXT);
        $table->addColumn('context', Types::JSON, ['default' => '{}']);
        $table->addColumn('extra', Types::JSON, ['default' => '{}']);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint(OperationsTable::NAME, ['operation_id'], ['id']);

        return $table;
    }
}
