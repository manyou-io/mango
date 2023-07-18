<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\DBAL\Connection;
use Manyou\Mango\ApiPlatform\SerializerInitializerContextBuilder;
use Manyou\Mango\Doctrine\Driver\Oci8InitializeSession;
use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Serializer\MoneyNormalizer;
use Manyou\Mango\TaskQueue\Doctrine\Table\TaskLogsTable;
use Manyou\Mango\TaskQueue\Doctrine\Table\TasksTable;
use Manyou\Mango\TaskQueue\Messenger\Middleware\TaskQueueMiddware;
use Manyou\Mango\TaskQueue\Monolog\TaskLogHandler;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Level;
use Monolog\Processor\PsrLogMessageProcessor;

use function dirname;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Manyou\\Mango\\', dirname(__DIR__, 3) . '/')
        ->exclude(dirname(__DIR__, 3) . '/{Bundle,Tests}');

    $services->set(SchemaProvider::class)->public();
    $services->set(Oci8InitializeSession::class);
    $services->set(TasksTable::class);
    $services->set(TaskLogsTable::class);
    $services->set(TaskQueueMiddware::class);

    $services->set('mango.monolog.processor.psr', PsrLogMessageProcessor::class)
        ->arg('$dateFormat', 'Y-m-d\TH:i:s.vp')
        ->arg('$removeUsedContextFields', true);

    $services->set('doctrine.dbal.logging_connection.configuration')
        ->parent('doctrine.dbal.connection.configuration')
        ->call('setSchemaManagerFactory', [service('doctrine.dbal.default_schema_manager_factory')]);

    $services->set('doctrine.dbal.logging_connection.event_manager')
        ->parent('doctrine.dbal.connection.event_manager');

    $services->set('doctrine.dbal.logging_connection')
        ->parent('doctrine.dbal.connection')
        ->public()
        ->args([
            ['url' => env('DATABASE_URL')->resolve()],
            service('doctrine.dbal.logging_connection.configuration'),
            service('doctrine.dbal.logging_connection.event_manager'),
        ]);

    $services->set('mango.doctrine.schema_provider.logging')
        ->class(SchemaProvider::class)
        ->arg(Connection::class, service('doctrine.dbal.logging_connection'));

    $services->set(TaskLogHandler::class)
        ->args([service('mango.doctrine.schema_provider.logging'), Level::Debug->value, false])
        ->call('setFormatter', [service('monolog.formatter.normalizer')]);

    $services->set('monolog.handler.task_queue', FingersCrossedHandler::class)
        ->args([service(TaskLogHandler::class), Level::Info->value, 30, true])
        ->call('pushProcessor', [service('mango.monolog.processor.psr')]);

    $services->set(SerializerInitializerContextBuilder::class);

    $services->set(MoneyNormalizer::class);
};
