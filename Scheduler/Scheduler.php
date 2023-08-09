<?php

declare(strict_types=1);

namespace Manyou\Mango\Scheduler;

use DateTimeImmutable;
use Manyou\Mango\Scheduler\Messenger\RecurringScheduleStamp;
use Manyou\Mango\Scheduler\Messenger\ScheduleMessageStamp;
use Manyou\Mango\Scheduler\Transport\DoctrineSchedulerTransport;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Scheduler\RecurringMessage;

use function ceil;
use function round;

class Scheduler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        #[Autowire(service: 'messenger.transport.scheduler')]
        private DoctrineSchedulerTransport $transport,
        private ClockInterface $clock = new Clock(),
    ) {
    }

    public function recurring(RecurringMessage $recurringMessage, ?string $key = null): void
    {
        $now         = $this->roundToSecond($this->clock->now());
        $nextRunDate = $this->ceilToSecond($recurringMessage->getTrigger()->getNextRunDate($now));
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

    public function upsert(string $key, DateTimeImmutable $availableAt, object $message): bool
    {
        $envelope = $this->messageBus->dispatch($message, [
            DelayStamp::delayUntil($this->roundToSecond($availableAt)),
            ScheduleMessageStamp::upsert($key),
            new TransportNamesStamp(['scheduler']),
        ]);

        return null !== $envelope->last(SentStamp::class);
    }

    public function insert(string $key, DateTimeImmutable $availableAt, object $message): bool
    {
        $envelope = $this->messageBus->dispatch($message, [
            DelayStamp::delayUntil($this->roundToSecond($availableAt)),
            ScheduleMessageStamp::insert($key),
            new TransportNamesStamp(['scheduler']),
        ]);

        return null !== $envelope->last(SentStamp::class);
    }

    public function update(string $key, DateTimeImmutable $availableAt, object $message): bool
    {
        $envelope = $this->messageBus->dispatch($message, [
            DelayStamp::delayUntil($this->roundToSecond($availableAt)),
            ScheduleMessageStamp::update($key),
            new TransportNamesStamp(['scheduler']),
        ]);

        return null !== $envelope->last(SentStamp::class);
    }

    public function unschedule(string $key): void
    {
        $this->transport->unschedule($key);
    }

    private function roundToSecond(DateTimeImmutable $date): DateTimeImmutable
    {
        $ts = $date->getTimestamp() + (int) round($date->format('u') / 1e6);

        return new DateTimeImmutable("@{$ts}");
    }

    private function ceilToSecond(DateTimeImmutable $date): DateTimeImmutable
    {
        $ts = $date->getTimestamp() + (int) ceil($date->format('u') / 1e6);

        return new DateTimeImmutable("@{$ts}");
    }
}
