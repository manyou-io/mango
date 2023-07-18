<?php

declare(strict_types=1);

namespace Manyou\Mango\TaskQueue\Messenger\Middleware;

use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\TaskQueue\Doctrine\Table\TasksTable;
use Manyou\Mango\TaskQueue\Enum\TaskStatus;
use Manyou\Mango\TaskQueue\Messenger\Stamp\ScheduleTaskStamp;
use Manyou\Mango\TaskQueue\Messenger\Stamp\TaskStamp;
use Manyou\Mango\TaskQueue\Monolog\TaskLogHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;
use Throwable;

class TaskQueueMiddware implements MiddlewareInterface
{
    public function __construct(
        private SchemaProvider $schema,
        private LoggerInterface $taskLogger,
    ) {
    }

    private function onSchedule(Envelope $envelope, StackInterface $stack, ScheduleTaskStamp $stamp): Envelope
    {
        return $this->schema->transactional(function () use ($envelope, $stack, $stamp) {
            $this->schema->createQuery()->insert(
                TasksTable::NAME,
                ['id' => $id = new Ulid(), 'status' => TaskStatus::QUEUEING],
            )->executeStatement();

            $stamp->callback($id);

            $envelope = $envelope
                ->withoutStampsOfType(ScheduleTaskStamp::class)
                ->with(new TaskStamp($id));

            return $stack->next()->handle($envelope, $stack);
        });
    }

    private function onRetry(Envelope $envelope, StackInterface $stack, TaskStamp $stamp): Envelope
    {
        return $this->schema->transactional(function () use ($envelope, $stack, $stamp) {
            $q = $this->schema->createQuery();

            $rowNum = $q
                ->update(TasksTable::NAME, ['status' => TaskStatus::QUEUEING])
                ->where($q->eq('id', $stamp->getId()), $q->eq('status', TaskStatus::FAILED))
                ->executeStatement();

            if ($rowNum !== 1) {
                // not in a retryable state
                return $envelope;
            }

            return $stack->next()->handle($envelope, $stack);
        });
    }

    private function onConsume(Envelope $envelope, StackInterface $stack, TaskStamp $stamp): Envelope
    {
        $q = $this->schema->createQuery();

        $rowNum = $q
            ->update(TasksTable::NAME, ['status' => TaskStatus::PROCESSING])
            ->where($q->eq('id', $stamp->getId()), $q->eq('status', TaskStatus::QUEUEING))
            ->executeStatement();

        if ($rowNum !== 1) {
            // not in a processable state
            return $envelope;
        }

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (Throwable $e) {
            $q = $this->schema->createQuery();
            $q->update(TasksTable::NAME, ['status' => TaskStatus::FAILED])
                ->where($q->eq('id', $stamp->getId()))
                ->executeStatement();

            $this->taskLogger->error($e->getMessage(), [
                TaskLogHandler::CONTEXT_KEY => $stamp->getId(),
                'exception' => $e,
            ]);

            return $envelope;
        }

        $q = $this->schema->createQuery();
        $q->update(TasksTable::NAME, ['status' => TaskStatus::COMPLETED])
            ->where($q->eq('id', $stamp->getId()))
            ->executeStatement();

        return $envelope;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var ScheduleTaskStamp|null */
        if (null !== $stamp = $envelope->last(ScheduleTaskStamp::class)) {
            return $this->onSchedule($envelope, $stack, $stamp);
        }

        /** @var TaskStamp|null */
        if (null === $stamp = $envelope->last(TaskStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        if (null === $envelope->last(ReceivedStamp::class)) {
            return $this->onRetry($envelope, $stack, $stamp);
        }

        return $this->onConsume($envelope, $stack, $stamp);
    }
}
