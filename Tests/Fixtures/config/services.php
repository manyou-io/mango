<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Mango\Doctrine\Driver\Oci8InitializeSession;
use Mango\Doctrine\SchemaProvider;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Mango\Tests\Fixtures\Tables\\', __DIR__ . '/../Tables/');

    $services->set(SchemaProvider::class)->public();
    $services->set(Oci8InitializeSession::class);
};
