<?php

declare(strict_types=1);

namespace Mango\Scheduler\Transport;

use Closure;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Types;
use Mango\Scheduler\Doctrine\Table\ScheduledMessagesTable;
use Mango\Scheduler\Messenger\ScheduleMessageStamp;
use PDOException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

use function implode;

class DoctrineSchedulerTransport implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    public function __construct(
        private DoctrineTransport $inner,
        private DBALConnection $driverConnection,
        private array $configuration,
        private LoggerInterface $logger,
    ) {
    }

    public function get(): iterable
    {
        return $this->inner->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->inner->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->inner->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        /** @var ScheduleMessageStamp|null */
        $stamp = $envelope->last(ScheduleMessageStamp::class);
        if (null === $stamp) {
            return $this->inner->send($envelope);
        }

        return $this->transactional(function ($commit) use ($envelope, $stamp) {
            $sentEnvelope = $this->inner->send($envelope);

            if (null === $messageId = $sentEnvelope->last(TransportMessageIdStamp::class)?->getId()) {
                throw new TransportException('No TransportMessageIdStamp found on the Envelope.');
            }

            try {
                $commit = $this->schedule($stamp, $messageId);

                return $commit ? $sentEnvelope : $envelope->withoutStampsOfType(SentStamp::class);
            } catch (DBALException $exception) {
                throw new TransportException($exception->getMessage(), 0, $exception);
            }
        });
    }

    private function executeQueryWithWriteLock(QueryBuilder $query): Result
    {
        $sql = $query->getSQL() . ' ' . $this->driverConnection->getDatabasePlatform()->getWriteLockSQL();

        return $this->driverConnection->executeQuery($sql, $query->getParameters(), $query->getParameterTypes());
    }

    private function executeStatementOnConflictDoNothing(QueryBuilder $query, array $conflict): int
    {
        $sql = $query->getSQL() . ' ON CONFLICT (' . implode(', ', $conflict) . ') DO NOTHING';

        return $this->driverConnection->executeStatement($sql, $query->getParameters(), $query->getParameterTypes());
    }

    private function cancel(mixed $messageId): int
    {
        if ($this->driverConnection->getDatabasePlatform() instanceof MySQLPlatform) {
            return $this->driverConnection->update(
                $this->configuration['table_name'],
                ['delivered_at' => '9999-12-31 23:59:59'],
                ['id' => $messageId, 'delivered_at' => null],
            );
        }

        return $this->driverConnection->delete(
            $this->configuration['table_name'],
            ['id' => $messageId, 'delivered_at' => null],
        );
    }

    public function unschedule(string $key): void
    {
        $this->transactional(function () use ($key) {
            $result = $this->executeQueryWithWriteLock(
                $this->driverConnection->createQueryBuilder()
                ->from(ScheduledMessagesTable::NAME, 'sm')
                ->select('sm.key AS "key"', 'sm.message_id AS "message_id"')
                ->where('sm.key = ?')
                ->setParameters([$key]),
            );

            if (false === $row = $result->fetchAssociative()) {
                return;
            }

            $this->driverConnection->delete(ScheduledMessagesTable::NAME, ['key' => $key]);
            $this->cancel($row['message_id']);
        });
    }

    private function schedule(ScheduleMessageStamp $stamp, mixed $messageId): bool
    {
        $this->logger->debug('Handling ScheduleMessageStamp.', ['key' => $stamp->key, 'action' => $stamp->action, 'message_id' => $messageId]);

        $result = $this->driverConnection->createQueryBuilder()
            ->from(ScheduledMessagesTable::NAME, 'sm')
            ->leftJoin('sm', $this->configuration['table_name'], 'm', 'sm.message_id = m.id')
            ->select('sm.key AS "key"', 'sm.message_id AS "message_id"', 'm.id AS "id"', 'm.delivered_at AS "delivered_at"')
            ->where('sm.key = ?')
            ->setParameters([$stamp->key], [Types::STRING]);

        if (false === $row = $result->fetchAssociative()) {
            $this->logger->debug('No schedule with the given key found.', ['key' => $stamp->key]);

            if ($stamp->isUpdate()) {
                $this->logger->debug('Cannot update since there is no schedule of the given key.', ['key' => $stamp->key]);

                return false;
            }

            // Row is not locked, so conflict may occur.
            $rowsAffected = $this->executeStatementOnConflictDoNothing(
                $this->driverConnection->createQueryBuilder()
                    ->insert(ScheduledMessagesTable::NAME)
                    ->values(['key' => '?', 'message_id' => '?'])
                    ->setParameters([$stamp->key, $messageId], [Types::STRING, Types::BIGINT]),
                ['key'],
            );

            $this->logger->debug('Insert schedule.', ['key' => $stamp->key, 'message_id' => $messageId, 'rows_affected' => $rowsAffected]);

            return 0 < $rowsAffected;
        }

        $this->logger->debug('Schedule with the given key is found.', ['key' => $stamp->key]);

        if (null === $row['id'] || null !== $row['delivered_at']) {
            if ($stamp->isUpdate()) {
                $this->logger->debug('Cannot update since the message has been consumed.', ['key' => $stamp->key, 'old_message_id' => $row['message_id'], 'delivered_at' => $row['delivered_at']]);

                return false;
            }

            $rowsAffected = $this->driverConnection->update(
                ScheduledMessagesTable::NAME,
                ['message_id' => $messageId],
                ['key' => $stamp->key, 'message_id' => $row['message_id']],
                [Types::STRING, Types::BIGINT],
            );

            $this->logger->debug('Replace inactive schedule.', ['key' => $stamp->key, 'message_id' => $messageId, 'old_message_id' => $row['message_id'], 'delivered_at' => $row['delivered_at'], 'rows_affected' => $rowsAffected]);

            return 0 < $rowsAffected;
        }

        if ($stamp->isInsert()) {
            $this->logger->debug('Cannot insert since queued message exists.', ['key' => $stamp->key]);

            return false;
        }

        $rowsAffected = $this->cancel($row['message_id']);
        $this->logger->debug('Cancel queued message.', ['key' => $stamp->key, 'old_message_id' => $row['message_id'], 'rows_affected' => $rowsAffected]);

        $rowsAffected = $this->driverConnection->update(
            ScheduledMessagesTable::NAME,
            ['message_id' => $messageId],
            ['key' => $stamp->key, 'message_id' => $row['message_id']],
            [Types::STRING, Types::BIGINT],
        );
        $this->logger->debug('Update schedule.', ['key' => $stamp->key, 'message_id' => $messageId, 'rows_affected' => $rowsAffected]);

        return 0 < $rowsAffected;
    }

    public function setup(): void
    {
        $this->inner->setup();
    }

    public function getMessageCount(): int
    {
        return $this->inner->getMessageCount();
    }

    public function all(?int $limit = null): iterable
    {
        return $this->inner->all($limit);
    }

    public function find(mixed $id): ?Envelope
    {
        return $this->inner->find($id);
    }

    private function transactional(Closure $func): mixed
    {
        $this->driverConnection->beginTransaction();
        $commit = true;

        try {
            $res = $func($commit);
            if ($commit) {
                $this->driverConnection->commit();
            }

            return $res;
        } catch (Throwable $e) {
            $commit = false;

            throw $e;
        } finally {
            if (! $commit) {
                try {
                    $this->driverConnection->rollBack();
                } catch (PDOException) {
                }
            }
        }
    }
}
