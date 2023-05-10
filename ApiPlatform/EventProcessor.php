<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EventProcessor implements ProcessorInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $event = $this->eventDispatcher->dispatch($data);

        if ($event instanceof EventWithResult) {
            return $event->getEventResult();
        }

        return $event;
    }
}
