<?php

declare(strict_types=1);

namespace Manyou\Mango\MessageLoop;

use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\MessageLoop\Doctrine\Table\MessageLoopsTable;
use Symfony\Component\Uid\Ulid;

class MessageLoopRepository
{
    public function __construct(
        private SchemaProvider $schema,
    ) {
    }

    public function resetLoop(string $key, Ulid $loopId): void
    {
        $insert = $this->schema->createQuery()
            ->insert(MessageLoopsTable::NAME, [
                'key' => $key,
                'loopId' => $loopId,
            ]);
        $this->schema->onConflictDoUpdate(
            $insert,
            conflict: ['key'],
            update: ['loopId' => $loopId],
            expectedRowNum: 1,
        );
    }

    public function deleteLoop(string $key): void
    {
        $q = $this->schema->createQuery();
        $q->delete(MessageLoopsTable::NAME)
            ->where($q->eq('key', $key))
            ->executeStatement();
    }

    public function isValidLoop(string $key, Ulid $loopId): bool
    {
        $q = $this->schema->createQuery();

        return false !== $q
            ->selectFrom(MessageLoopsTable::NAME, 'key')
            ->where($q->eq('loopId', $loopId), $q->eq('key', $key))
            ->setMaxResults(1)
            ->fetchAssociativeFlat();
    }
}
