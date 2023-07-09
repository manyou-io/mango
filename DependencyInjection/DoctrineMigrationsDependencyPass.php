<?php

declare(strict_types=1);

namespace Manyou\Mango\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/** Set custom dependency for Doctrine Migrations. */
class DoctrineMigrationsDependencyPass implements CompilerPassInterface
{
    public function __construct(
        private array $services = [],
        private array $factories = [],
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $diDefinition = $container->getDefinition('doctrine.migrations.dependency_factory');

        foreach ($this->services as $doctrineId => $symfonyId) {
            $diDefinition->addMethodCall('setDefinition', [$doctrineId, new ServiceClosureArgument(new Reference($symfonyId))]);
        }

        foreach ($this->factories as $doctrineId => $symfonyId) {
            $diDefinition->addMethodCall('setDefinition', [$doctrineId, new Reference($symfonyId)]);
        }
    }
}
