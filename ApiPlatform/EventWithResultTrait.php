<?php

declare(strict_types=1);

namespace Manyou\Mango\ApiPlatform;

use function property_exists;

trait EventWithResultTrait
{
    private mixed $eventResult;

    public function getEventResult(): mixed
    {
        if (! property_exists($this, 'eventResult')) {
            return $this;
        }

        return $this->eventResult;
    }

    public function setEventResult(mixed $result): self
    {
        $this->eventResult = $result;

        return $this;
    }

    public function acceptEventResult(EventWithResult $event): self
    {
        $this->setEventResult($event->getEventResult());

        return $this;
    }
}
