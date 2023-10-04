<?php

declare(strict_types=1);

namespace Mango\Bundle;

use Doctrine\Migrations\Provider\SchemaProvider as SchemaProviderInterface;
use Mango\DependencyInjection\DoctrineEnableSavepointPass;
use Mango\DependencyInjection\DoctrineMigrationsDependencyPass;
use Mango\DependencyInjection\DoctrineTypePass;
use Mango\DependencyInjection\HttpKernelControllerPass;
use Mango\Doctrine\SchemaProvider;
use Mango\Doctrine\Type\UlidType;
use Mango\Doctrine\Type\UsDateTimeImmutableType;
use Mango\Doctrine\Type\UuidType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class MangoBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new HttpKernelControllerPass());

        $container->addCompilerPass(
            new DoctrineTypePass([
                'ulid' => UlidType::class,
                'uuid' => UuidType::class,
                UsDateTimeImmutableType::NAME => UsDateTimeImmutableType::class,
            ]),
            priority: 1,
        );

        $container->addCompilerPass(
            new DoctrineMigrationsDependencyPass([SchemaProviderInterface::class => SchemaProvider::class]),
        );

        $container->addCompilerPass(new DoctrineEnableSavepointPass());
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/Resources/config/services.php');
    }

    public function getPath(): string
    {
        return __DIR__;
    }
}
