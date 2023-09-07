<?php

declare(strict_types=1);

namespace Mango\Doctrine\Schema;

use Mango\Doctrine\Table;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mango.doctrine.table_builder')]
interface TableBuilder
{
    public function getName(): string;

    public function build(Table $table): void;
}
