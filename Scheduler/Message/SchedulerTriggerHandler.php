<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler\Message;

use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Scheduler\Doctrine\Table\ScheduledMessagesTable;
use Manyou\Mango\Scheduler\Messenger\ScheduledMessageStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[AsMessageHandler]
class SchedulerTriggerHandler
{
    public function __construct(
        private SchemaProvider $schema,
        private MessageBusInterface $messageBus,
        private ClockInterface $clock,
        private SerializerInterface $serializer,
    ) {
    }

    public function __invoke(SchedulerTrigger $message): void
    {
        foreach ($this->getAvailableMessages() as $row) {
            $this->messageBus->dispatch($this->serializer->decode($row['envelope']), [
                new ScheduledMessageStamp($row['key']),
                new DispatchAfterCurrentBusStamp(),
            ]);
        }
    }

    private function getAvailableMessages(): iterable
    {
        $q = $this->schema->createQuery();

        $q->selectFrom([ScheduledMessagesTable::NAME, 's'], 'key', 'envelope');
        $q->where(
            $q->lte('availableAt', $this->clock->now()),
            $q->or(
                $q->gt('availableAt', ['s', 'lastDispatchedAt']),
                $q->isNull('lastDispatchedAt'),
            ),
        );

        while ($row = $q->fetchAssociativeFlat()) {
            yield $row;
        }
    }
}
