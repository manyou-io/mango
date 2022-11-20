<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\DBAL\Connection;
use Manyou\Mango\ApiPlatform\SerializerInitializerContextBuilder;
use Manyou\Mango\Doctrine\Driver\Oci8InitializeSession;
use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Operation\Doctrine\TableProvider\OperationLogsTable;
use Manyou\Mango\Operation\Doctrine\TableProvider\OperationsTable;
use Manyou\Mango\Operation\Messenger\Middleware\OperationMiddware;
use Manyou\Mango\Operation\Monolog\OperationLogHandler;
use Manyou\Mango\Operation\Repository\OperationRepository;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Level;
use Monolog\Processor\PsrLogMessageProcessor;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(SchemaProvider::class)->public();
    $services->set(Oci8InitializeSession::class);
    $services->set(OperationsTable::class);
    $services->set(OperationLogsTable::class);
    $services->set(OperationMiddware::class);
    $services->set(OperationRepository::class)->public();

    $services->set('mango.monolog.processor.psr', PsrLogMessageProcessor::class)
        ->arg('$dateFormat', 'Y-m-d\TH:i:s.vp')
        ->arg('$removeUsedContextFields', true);

    $services->set('doctrine.dbal.logging_connection.configuration')
        ->parent('doctrine.dbal.connection.configuration');

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

    $services->set(OperationLogHandler::class)
        ->args([service('mango.doctrine.schema_provider.logging'), Level::Debug->value, false])
        ->call('setFormatter', [service('monolog.formatter.normalizer')]);

    $services->set('monolog.handler.operation', FingersCrossedHandler::class)
        ->args([service(OperationLogHandler::class), Level::Info->value, 30, true])
        ->call('pushProcessor', [service('mango.monolog.processor.psr')]);

    $services->set(SerializerInitializerContextBuilder::class);
};
