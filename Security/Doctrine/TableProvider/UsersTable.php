<?php

declare(strict_types=1);

namespace Manyou\Mango\Security\Doctrine\TableProvider;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;

class UsersTable implements TableProvider
{
    public const NAME = 'users';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', 'ulid');
        $table->addColumn('username', Types::STRING, ['length' => 255]);
        $table->addColumn('password', Types::STRING, ['length' => 255]);
        $table->setPrimaryKey(['id']);

        return $table;
    }
}
