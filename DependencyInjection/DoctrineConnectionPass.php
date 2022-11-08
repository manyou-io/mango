<?php

declare(strict_types=1);

namespace Manyou\Mango\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Register additional Doctrine connections. */
class DoctrineConnectionPass implements CompilerPassInterface
{
    public function __construct(private array $connections)
    {
    }

    public function process(ContainerBuilder $container)
    {
        $connections = $container->hasParameter('doctrine.connections')
            ? $container->getParameter('doctrine.connections')
            : [];

        $connections += $this->connections;

        $container->setParameter('doctrine.connections', $connections);
    }
}
