<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Contract;

use Manyou\Mango\Doctrine\Table;
use Doctrine\DBAL\Schema\Schema;

interface TableProvider
{
    public function __invoke(Schema $schema): Table;
}
