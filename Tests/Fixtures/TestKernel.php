<?php

declare(strict_types=1);

namespace Mango\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Mango\DependencyInjection\DoctrineTypePass;
use Mango\Doctrine\Schema\TableBuilder;
use Mango\Doctrine\Type\UlidType;
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
            ->registerForAutoconfiguration(TableBuilder::class)
            ->addTag('mango.doctrine.table_builder');

        $container->addCompilerPass(
            new DoctrineTypePass([
                'ulid' => UlidType::class,
            ]),
            priority: 1,
        );
    }
}
