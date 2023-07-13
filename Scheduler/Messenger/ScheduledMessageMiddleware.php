<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Messenger;

use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Scheduler\Doctrine\Table\ScheduledMessagesTable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class ScheduledMessageMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SchemaProvider $schema,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var ScheduledMessageStamp|null */
        $stamp = $envelope->last(ScheduledMessageStamp::class);

        if (null !== $stamp) {
            return $this->schema->transactional(function () use ($envelope, $stack, $stamp) {
                $q = $this->schema->createQuery();
                $q->update([ScheduledMessagesTable::NAME, 's'], ['lastDispatchedAt' => $this->clock->now()])->where(
                    $q->eq('key', $stamp->getKey()),
                    $q->or(
                        $q->gt('availableAt', ['s', 'lastDispatchedAt']),
                        $q->isNull('lastDispatchedAt'),
                    ),
                );
                if ($q->executeStatement() !== 1) {
                    $this->logger->warning('Scheduled message was already dispatched.', ['key' => $stamp->getKey()]);

                    return $envelope;
                }

                return $stack->next()->handle($envelope->withoutStampsOfType(ScheduledMessageStamp::class), $stack);
            });
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
