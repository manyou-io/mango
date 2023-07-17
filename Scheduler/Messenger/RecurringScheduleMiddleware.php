<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Messenger;

use Manyou\Mango\Scheduler\Scheduler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class RecurringScheduleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Scheduler $scheduler,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        if (! $envelope->last(HandledStamp::class)) {
            return $envelope;
        }

        /** @var RecurringScheduleStamp|null */
        $stamp = $envelope->last(RecurringScheduleStamp::class);
        if (! $stamp) {
            return $envelope;
        }

        $this->scheduler->recurring($stamp->recurringMessage, $stamp->key);

        return $envelope;
    }
}
