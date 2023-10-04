<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use function dirname;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Mango\\', dirname(__DIR__, 3) . '/')
        ->exclude(dirname(__DIR__, 3) . '/{Bundle,Tests}');
};
