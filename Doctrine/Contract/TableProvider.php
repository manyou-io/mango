<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Contract;

use Doctrine\DBAL\Schema\Schema;
use Manyou\Mango\Doctrine\Table;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mango.doctrine.table_provider')]
interface TableProvider
{
    public function __invoke(Schema $schema): Table;
}
