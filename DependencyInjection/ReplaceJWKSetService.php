<?php

declare(strict_types=1);

namespace Manyou\Mango\DependencyInjection;

use Manyou\Mango\Jose\CachedJWKSLoader;
use Manyou\Mango\Jose\JWKSet;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ReplaceJWKSetService implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->setDefinition(
            'jose.key_set.lexik_jose_bridge.signature',
            new Definition(JWKSet::class, [[], new Reference(CachedJWKSLoader::class)]),
        );
    }
}
