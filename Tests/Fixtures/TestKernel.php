<?php

declare(strict_types=1);

namespace Manyou\Mango\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Manyou\Mango\DependencyInjection\DoctrineTypePass;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Type\UlidType;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
        ];
    }

    protected function build(ContainerBuilder $container): void
    {
        $container
            ->registerForAutoconfiguration(TableProvider::class)
            ->addTag('mango.doctrine.table_provider');

        $container->addCompilerPass(
            new DoctrineTypePass([
                'ulid' => UlidType::class,
            ]),
            priority: 1,
        );
    }
}
