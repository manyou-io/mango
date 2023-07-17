<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler;

use DateTimeImmutable;
use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Scheduler\Doctrine\Table\ScheduledMessagesTable;
use Manyou\Mango\Scheduler\Doctrine\Table\SchedulerTriggersTable;
use Manyou\Mango\Scheduler\Message\SchedulerTrigger;
use Manyou\Mango\Scheduler\Messenger\RecurringScheduleStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Scheduler\RecurringMessage;

class Scheduler
{
    public function __construct(
        private SchemaProvider $schema,
        private MessageBusInterface $messageBus,
        private SerializerInterface $serializer,
        private ClockInterface $clock,
    ) {
    }

    public function recurring(RecurringMessage $recurringMessage, ?string $key = null): void
    {
        $this->upsert(
            $key ??= $recurringMessage->getId(),
            $recurringMessage->getTrigger()->getNextRunDate($this->clock->now()),
            Envelope::wrap($recurringMessage->getMessage(), [new RecurringScheduleStamp($key, $recurringMessage)]),
        );
    }

    public function upsert(string $key, DateTimeImmutable $availableAt, object $message): void
    {
        $this->schedule($key, $availableAt, $message, function (string $key, DateTimeImmutable $availableAt, array $envelope): bool {
            return 0 < $this->schema->onConflictDoUpdate(
                $this->schema->createQuery()->insert(
                    ScheduledMessagesTable::NAME,
                    [
                        'key' => $key,
                        'availableAt' => $availableAt,
                        'envelope' => $envelope,
                    ],
                ),
                conflict: ['key'],
                update: ['availableAt' => $availableAt, 'envelope' => $envelope],
                expectedRowNum: 1,
            );
        });
    }

    public function insert(string $key, DateTimeImmutable $availableAt, object $message): bool
    {
        return $this->schedule($key, $availableAt, $message, function (string $key, DateTimeImmutable $availableAt, array $envelope): bool {
            return 0 < $this->schema->onConflictDoNothing(
                $this->schema->createQuery()->insert(
                    ScheduledMessagesTable::NAME,
                    [
                        'key' => $key,
                        'availableAt' => $availableAt,
                        'envelope' => $envelope,
                    ],
                ),
                conflict: ['key'],
            );
        });
    }

    public function update(string $key, DateTimeImmutable $availableAt, object $message): bool
    {
        return $this->schedule($key, $availableAt, $message, function (string $key, DateTimeImmutable $availableAt, array $envelope): bool {
            $q = $this->schema->createQuery();
            $q->update(ScheduledMessagesTable::NAME, [
                'availableAt' => $availableAt,
                'envelope' => $envelope,
            ])->where($q->eq('key', $key));

            return $q->executeStatement() > 0;
        });
    }

    private function dispatchTrigger(DateTimeImmutable $delayUntil): bool
    {
        $q = $this->schema->createQuery()
            ->insert(SchedulerTriggersTable::NAME, ['delayUntil' => $delayUntil]);

        return $this->schema->onConflictDoNothing($q, ['delayUntil']) > 0;
    }

    public function unschedule(string $key): bool
    {
        $q = $this->schema->createQuery();
        $q->delete(ScheduledMessagesTable::NAME)->where(
            $q->eq('key', $key),
        );

        return $q->executeStatement() > 0;
    }

    private function schedule(string $key, DateTimeImmutable $availableAt, object $message, callable $scheduleFn): bool
    {
        $envelope = Envelope::wrap($message);

        $availableAt = new DateTimeImmutable("@{$availableAt->getTimestamp()}");

        if (! $scheduleFn($key, $availableAt, $this->serializer->encode($envelope))) {
            return false;
        }

        $this->schema->transactional(function () use ($availableAt): void {
            if ($this->dispatchTrigger($availableAt)) {
                $this->messageBus->dispatch(new SchedulerTrigger(), [DelayStamp::delayUntil($availableAt)]);
            }
        });

        return true;
    }
}
