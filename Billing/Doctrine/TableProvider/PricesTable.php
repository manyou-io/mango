<?php

declare(strict_types=1);

namespace Manyou\Mango\Billing\Doctrine\TableProvider;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Manyou\Mango\Billing\Doctrine\Type\CurrencyType;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;

class PricesTable implements TableProvider
{
    public const NAME = 'prices';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', 'ulid');
        $table->addColumn('amount', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('currency', CurrencyType::NAME, CurrencyType::DEFAULT_OPTIONS);
        $table->setPrimaryKey(['id']);

        return $table;
    }
}
