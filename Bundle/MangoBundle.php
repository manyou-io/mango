<?php

declare(strict_types=1);

namespace Mango\Bundle;

use Doctrine\Migrations\Provider\SchemaProvider as SchemaProviderInterface;
use LogicException;
use Mango\DependencyInjection\DoctrineConnectionPass;
use Mango\DependencyInjection\DoctrineEnableSavepointPass;
use Mango\DependencyInjection\DoctrineMigrationsDependencyPass;
use Mango\DependencyInjection\DoctrineTypePass;
use Mango\DependencyInjection\HttpKernelControllerPass;
use Mango\DependencyInjection\MessengerMiddlewarePass;
use Mango\DependencyInjection\MonologChannelPass;
use Mango\Doctrine\SchemaProvider;
use Mango\Doctrine\Type\LogLevelType;
use Mango\Doctrine\Type\UlidType;
use Mango\Doctrine\Type\UsDateTimeImmutableType;
use Mango\Doctrine\Type\UuidType;
use Mango\HttpKernel\AsDtoInitializer;
use Mango\Scheduler\Messenger\RecurringScheduleMiddleware;
use Mango\TaskQueue\Doctrine\Type\TaskStatusType;
use Mango\TaskQueue\Messenger\Middleware\TaskQueueMiddware;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function get_object_vars;
use function sprintf;

class MangoBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            AsDtoInitializer::class,
            static function (ChildDefinition $definition, AsDtoInitializer $attribute, ReflectionClass|ReflectionMethod $reflector): void {
                $tagAttributes = get_object_vars($attribute);
                if ($reflector instanceof ReflectionMethod) {
                    if (isset($tagAttributes['method'])) {
                        throw new LogicException(
                            sprintf('AsDtoInitializer attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name),
                        );
                    }

                    $tagAttributes['method'] = $reflector->getName();
                }

                $definition->addTag('mango.http_kernel.dto_initializer', $tagAttributes);
            },
        );

        $container->addCompilerPass(new HttpKernelControllerPass());

        $container->addCompilerPass(
            new MessengerMiddlewarePass(['id' => TaskQueueMiddware::class]),
            priority: 1,
        );

        $container->addCompilerPass(
            new MessengerMiddlewarePass(['id' => RecurringScheduleMiddleware::class]),
            priority: 2,
        );

        $container->addCompilerPass(
            new DoctrineTypePass([
                TaskStatusType::NAME => TaskStatusType::class,
                LogLevelType::NAME => LogLevelType::class,
                'ulid' => UlidType::class,
                'uuid' => UuidType::class,
                UsDateTimeImmutableType::NAME => UsDateTimeImmutableType::class,
            ]),
            priority: 1,
        );

        $container->addCompilerPass(
            new DoctrineMigrationsDependencyPass([SchemaProviderInterface::class => SchemaProvider::class]),
        );

        $container->addCompilerPass(
            new MonologChannelPass(
                ['task'],
                ['monolog.handler.task_queue' => 'task'],
            ),
            priority: 1,
        );

        $container->addCompilerPass(
            new DoctrineConnectionPass(['logging' => 'doctrine.dbal.logging_connection']),
            priority: 1,
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
