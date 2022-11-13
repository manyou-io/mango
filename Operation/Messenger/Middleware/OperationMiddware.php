<?php

declare(strict_types=1);

namespace Manyou\Mango\Operation\Messenger\Middleware;

use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\Operation\Doctrine\TableProvider\OperationsTable;
use Manyou\Mango\Operation\Enum\OperationStatus;
use Manyou\Mango\Operation\Messenger\Stamp\CreateOperationStamp;
use Manyou\Mango\Operation\Messenger\Stamp\OperationStamp;
use Manyou\Mango\Operation\Monolog\OperationLogHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;
use Throwable;

class OperationMiddware implements MiddlewareInterface
{
    public function __construct(
        private SchemaProvider $schema,
        private LoggerInterface $operationLogger,
    ) {
    }

    private function onCreate(Envelope $envelope, StackInterface $stack, CreateOperationStamp $stamp): Envelope
    {
        return $this->schema->getConnection()->transactional(function () use ($envelope, $stack, $stamp) {
            $this->schema->createQuery()->insert(
                OperationsTable::NAME,
                ['id' => $id = new Ulid(), 'status' => OperationStatus::QUEUEING],
            )->executeStatement();

            $stamp->callback($id);

            $envelope = $envelope
                ->withoutStampsOfType(CreateOperationStamp::class)
                ->with(new OperationStamp($id));

            return $stack->next()->handle($envelope, $stack);
        });
    }

    private function onRetry(Envelope $envelope, StackInterface $stack, OperationStamp $stamp): Envelope
    {
        return $this->schema->getConnection()->transactional(function () use ($envelope, $stack, $stamp) {
            $q = $this->schema->createQuery();

            $rowNum = $q
                ->update([OperationsTable::NAME, 't'], ['status' => OperationStatus::QUEUEING])
                ->where($q->eq('t.id', $stamp->getId()), $q->eq('t.status', OperationStatus::FAILED))
                ->executeStatement();

            if ($rowNum !== 1) {
                // not in a retryable state
                return $envelope;
            }

            return $stack->next()->handle($envelope, $stack);
        });
    }

    private function onConsume(Envelope $envelope, StackInterface $stack, OperationStamp $stamp): Envelope
    {
        return $this->schema->getConnection()->transactional(function () use ($envelope, $stack, $stamp) {
            $q = $this->schema->createQuery();

            $rowNum = $q
                ->update([OperationsTable::NAME, 't'], ['status' => OperationStatus::PROCESSING])
                ->where($q->eq('t.id', $stamp->getId()), $q->eq('t.status', OperationStatus::QUEUEING))
                ->executeStatement();

            if ($rowNum !== 1) {
                // not in a processable state
                return $envelope;
            }

            try {
                $envelope = $stack->next()->handle($envelope, $stack);
            } catch (Throwable $e) {
                $q = $this->schema->createQuery();
                $q->update([OperationsTable::NAME, 't'], ['status' => OperationStatus::FAILED])
                    ->where($q->eq('t.id', $stamp->getId()))
                    ->executeStatement();

                $this->operationLogger->error($e->getMessage(), [
                    OperationLogHandler::CONTEXT_KEY => $stamp->getId(),
                    'exception' => $e,
                ]);

                return $envelope;
            }

            $q = $this->schema->createQuery();
            $q->update([OperationsTable::NAME, 't'], ['status' => OperationStatus::COMPLETED])
                ->where($q->eq('t.id', $stamp->getId()))
                ->executeStatement();

            return $envelope;
        });
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var CreateOperationStamp|null */
        if (null !== $stamp = $envelope->last(CreateOperationStamp::class)) {
            return $this->onCreate($envelope, $stack, $stamp);
        }

        /** @var OperationStamp|null */
        if (null !== $stamp = $envelope->last(OperationStamp::class)) {
            if (null !== $envelope->last(ReceivedStamp::class)) {
                return $this->onConsume($envelope, $stack, $stamp);
            }

            return $this->onRetry($envelope, $stack, $stamp);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
