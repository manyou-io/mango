<?php

declare(strict_types=1);

namespace Mango\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Register custom column types to Doctrine DBAL. */
class DoctrineTypePass implements CompilerPassInterface
{
    public function __construct(private array $typesToRegister)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('doctrine.dbal.connection_factory.types')) {
            return;
        }

        $typeDefinition = $container->getParameter('doctrine.dbal.connection_factory.types');

        foreach ($this->typesToRegister as $typeName => $className) {
            if (! isset($typeDefinition[$typeName])) {
                $typeDefinition[$typeName] = ['class' => $className];
            }
        }

        $container->setParameter('doctrine.dbal.connection_factory.types', $typeDefinition);
    }
}
