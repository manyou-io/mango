<?php

declare(strict_types=1);

namespace Manyou\Mango\DependencyInjection;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_map;
use function array_merge;
use function array_unique;
use function strpos;
use function substr;

class MonologChannelPass implements CompilerPassInterface
{
    private array $handlersToChannels;

    public function __construct(
        private array $additionalChannels = [],
        array $handlersToChannels = [],
    ) {
        $this->handlersToChannels = array_map([$this, 'processHandlersToChannels'], $handlersToChannels);
    }

    public function process(ContainerBuilder $container): void
    {
        if ($this->additionalChannels !== []) {
            $additionalChannels = $container->hasParameter('monolog.additional_channels')
                ? $container->getParameter('monolog.additional_channels')
                : [];

            $additionalChannels = array_unique(array_merge($additionalChannels, $this->additionalChannels));

            $container->setParameter('monolog.additional_channels', $additionalChannels);
        }

        if ($this->handlersToChannels !== []) {
            $handlersToChannels = $container->hasParameter('monolog.handlers_to_channels')
                ? $container->getParameter('monolog.handlers_to_channels')
                : [];

            $handlersToChannels += $this->handlersToChannels;

            $container->setParameter('monolog.handlers_to_channels', $handlersToChannels);
        }
    }

    /** @see \Symfony\Bundle\MonologBundle\DependencyInjection\Configuration */
    private function processHandlersToChannels(string|array|null $channels): array
    {
        if ($channels === null) {
            return null;
        }

        $channels = (array) $channels;

        $isExclusive = null;

        $elements = [];
        foreach ($channels as $element) {
            if (0 === strpos($element, '!')) {
                if (false === $isExclusive) {
                    throw new InvalidArgumentException('Cannot combine exclusive/inclusive definitions in channels list.');
                }

                $elements[]  = substr($element, 1);
                $isExclusive = true;
            } else {
                if (true === $isExclusive) {
                    throw new InvalidArgumentException('Cannot combine exclusive/inclusive definitions in channels list');
                }

                $elements[]  = $element;
                $isExclusive = false;
            }
        }

        if ($elements === []) {
            return null;
        }

        return ['type' => $isExclusive ? 'exclusive' : 'inclusive', 'elements' => $elements];
    }
}
