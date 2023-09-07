<?php

declare(strict_types=1);

namespace Mango\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_column;
use function array_search;
use function array_splice;

/** Inject custom middleware into messenger buses. */
class MessengerMiddlewarePass implements CompilerPassInterface
{
    private array $middlewaresToAdd;

    public function __construct(array ...$middlewaresToAdd)
    {
        $this->middlewaresToAdd = $middlewaresToAdd;
    }

    public function process(ContainerBuilder $container): void
    {
        $busIds = [];

        foreach ($container->findTaggedServiceIds('messenger.bus') as $busId => $tags) {
            $busIds[] = $busId;

            if ($container->hasParameter($busMiddlewareParameter = $busId . '.middleware')) {
                $middlewares   = $container->getParameter($busMiddlewareParameter);
                $middlewareIds = array_column($middlewares, 'id');

                // send_message is the first middleware comes after custom middlewares
                if (false === $positionAfter = array_search('send_message', $middlewareIds, true)) {
                    continue;
                }

                array_splice($middlewares, $positionAfter, 0, $this->middlewaresToAdd);
                $container->setParameter($busMiddlewareParameter, $middlewares);
            }
        }
    }
}
