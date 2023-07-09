<?php

declare(strict_types=1);

namespace Manyou\Mango\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DoctrineEnableSavepointPass implements CompilerPassInterface
{
    public function __construct(private bool $value = true)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.connections')) {
            return;
        }

        foreach ($container->getParameter('doctrine.connections') as $id) {
            $container->getDefinition($id)
                ->removeMethodCall('setNestTransactionsWithSavepoints')
                ->addMethodCall('setNestTransactionsWithSavepoints', [$this->value]);
        }
    }
}
