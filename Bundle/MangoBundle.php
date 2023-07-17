<?php

declare(strict_types=1);

namespace Manyou\Mango\Bundle;

use Doctrine\Migrations\Provider\SchemaProvider as SchemaProviderInterface;
use LogicException;
use Manyou\Mango\DependencyInjection\DoctrineConnectionPass;
use Manyou\Mango\DependencyInjection\DoctrineEnableSavepointPass;
use Manyou\Mango\DependencyInjection\DoctrineMigrationsDependencyPass;
use Manyou\Mango\DependencyInjection\DoctrineTypePass;
use Manyou\Mango\DependencyInjection\HttpKernelControllerPass;
use Manyou\Mango\DependencyInjection\MessengerMiddlewarePass;
use Manyou\Mango\DependencyInjection\MonologChannelPass;
use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Doctrine\Type\LogLevelType;
use Manyou\Mango\Doctrine\Type\ObjectJsonType;
use Manyou\Mango\Doctrine\Type\UlidType;
use Manyou\Mango\Doctrine\Type\UsDateTimeImmutableType;
use Manyou\Mango\Doctrine\Type\UuidType;
use Manyou\Mango\HttpKernel\AsDtoInitializer;
use Manyou\Mango\Scheduler\Messenger\RecurringScheduleMiddleware;
use Manyou\Mango\Scheduler\Messenger\ScheduledMessageMiddleware;
use Manyou\Mango\TaskQueue\Doctrine\Type\TaskStatusType;
use Manyou\Mango\TaskQueue\Messenger\Middleware\TaskQueueMiddware;
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
            new MessengerMiddlewarePass(['id' => ScheduledMessageMiddleware::class]),
            priority: 2,
        );

        $container->addCompilerPass(
            new MessengerMiddlewarePass(['id' => RecurringScheduleMiddleware::class]),
            priority: 3,
        );

        $container->addCompilerPass(
            new DoctrineTypePass([
                TaskStatusType::NAME => TaskStatusType::class,
                LogLevelType::NAME => LogLevelType::class,
                'ulid' => UlidType::class,
                'uuid' => UuidType::class,
                UsDateTimeImmutableType::NAME => UsDateTimeImmutableType::class,
                ObjectJsonType::NAME => ObjectJsonType::class,
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
