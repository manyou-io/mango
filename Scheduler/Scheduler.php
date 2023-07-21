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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Scheduler\RecurringMessage;

use function round;

class Scheduler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private SchemaProvider $schema,
        private MessageBusInterface $messageBus,
        private SerializerInterface $serializer,
        private ClockInterface $clock = new Clock(),
    ) {
    }

    public function recurring(RecurringMessage $recurringMessage, ?string $key = null): void
    {
        $now         = $this->clock->now();
        $nextRunDate = $recurringMessage->getTrigger()->getNextRunDate($now);
        $key       ??= $recurringMessage->getId();

        if (! $nextRunDate) {
            $this->logger->info('Scheduler: no next recurring date', ['key' => $key, 'now' => $now]);

            return;
        }

        $this->logger->info('Scheduler: recurring', ['key' => $key, 'nextRunDate' => $nextRunDate, 'now' => $now]);

        $this->upsert(
            $key,
            $nextRunDate,
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

        $availableAt = $this->roundToSecond($availableAt);

        if (! $scheduleFn($key, $availableAt, $this->serializer->encode($envelope))) {
            $this->logger?->notice('Scheduler: schedule was not updated or inserted', [
                'key' => $key,
                'availableAt' => $availableAt,
            ]);

            return false;
        }

        $this->schema->transactional(function () use ($availableAt): void {
            if ($this->dispatchTrigger($availableAt)) {
                $this->logger?->info('Scheduler: dispatching trigger', ['availableAt' => $availableAt]);
                $this->messageBus->dispatch(new SchedulerTrigger(), [DelayStamp::delayUntil($availableAt)]);
            }
        });

        return true;
    }

    private function roundToSecond(DateTimeImmutable $date): DateTimeImmutable
    {
        $ts = $date->getTimestamp() + (int) round($date->format('u') / 1e6);

        return new DateTimeImmutable("@{$ts}");
    }
}
