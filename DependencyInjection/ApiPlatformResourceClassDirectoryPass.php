<?php

declare(strict_types=1);

namespace Mango\DependencyInjection;

use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ApiPlatformResourceClassDirectoryPass implements CompilerPassInterface
{
    public function __construct(
        private array $paths = [],
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if ($this->paths === []) {
            return;
        }

        $resourceDirs = $container->hasParameter('api_platform.resource_class_directories')
            ? $container->getParameter('api_platform.resource_class_directories')
            : [];

        foreach ($this->paths as $path) {
            $resourceDirs[] = $path;
            $container->addResource(new DirectoryResource($path, '/\.(xml|ya?ml|php)$/'));
        }

        $container->setParameter('api_platform.resource_class_directories', $resourceDirs);
    }
}
