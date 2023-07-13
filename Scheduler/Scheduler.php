<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler;

use DateTimeImmutable;
use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Scheduler\Doctrine\Table\ScheduledMessagesTable;
use Manyou\Mango\Scheduler\Doctrine\Table\SchedulerTriggersTable;
use Manyou\Mango\Scheduler\Message\SchedulerTrigger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

use function array_map;

class Scheduler
{
    public function __construct(
        private SchemaProvider $schema,
        private MessageBusInterface $messageBus,
        private SerializerInterface $serializer,
    ) {
    }

    public function schedule(string $key, DateTimeImmutable $availableAt, object $message, array $stamps = [], bool $onConflictUpdate = true): void
    {
        $envelope = Envelope::wrap($message, $stamps);

        // trunc availableAt to seconds
        $availableAt = $availableAt->setTime(...array_map(
            static fn ($v) => (int) $availableAt->format($v),
            ['H', 'i', 's'],
        ));

        $this->schema->transactional(function () use ($key, $availableAt, $envelope, $onConflictUpdate) {
            if (! $this->scheduleMessage($key, $availableAt, $envelope, $onConflictUpdate)) {
                return;
            }

            if ($this->dispatchTrigger($availableAt)) {
                $this->messageBus->dispatch(new SchedulerTrigger(), [DelayStamp::delayUntil($availableAt)]);
            }
        });
    }

    private function dispatchTrigger(DateTimeImmutable $delayUntil): bool
    {
        $q = $this->schema->createQuery()
            ->insert(SchedulerTriggersTable::NAME, ['delayUntil' => $delayUntil]);

        $rowsAffected = $this->schema->onConflictDoNothing($q, ['delayUntil']);

        return $rowsAffected === 1;
    }

    private function scheduleMessage(string $key, DateTimeImmutable $availableAt, Envelope $envelope, bool $onConflictUpdate): bool
    {
        $envelope = $this->serializer->encode($envelope);

        $insert = $this->schema->createQuery()->insert(
            ScheduledMessagesTable::NAME,
            [
                'key' => $key,
                'availableAt' => $availableAt,
                'envelope' => $envelope,
            ],
        );

        if ($onConflictUpdate) {
            $this->schema->onConflictDoUpdate(
                $insert,
                conflict: ['key'],
                update: ['availableAt' => $availableAt, 'envelope' => $envelope],
                expectedRowNum: 1,
            );

            return true;
        }

        return 1 === $this->schema->onConflictDoNothing($insert, conflict: ['key']);
    }
}
