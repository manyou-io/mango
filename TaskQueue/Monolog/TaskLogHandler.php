<?php

declare(strict_types=1);

namespace Manyou\Mango\TaskQueue\Monolog;

use Manyou\Mango\Doctrine\SchemaProvider;
use Manyou\Mango\TaskQueue\Doctrine\Table\TaskLogsTable;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\FormattableHandlerTrait;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Uid\Ulid;

class TaskLogHandler extends AbstractHandler implements FormattableHandlerInterface
{
    use FormattableHandlerTrait;

    public const CONTEXT_KEY = 'task_id';

    public function __construct(
        private SchemaProvider $schema,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    public function handle(LogRecord $record): bool
    {
        if (! $this->isHandling($record)) {
            return false;
        }

        $taskId = $record->context[self::CONTEXT_KEY] ?? null;

        if (! $taskId instanceof Ulid) {
            return false;
        }

        $record->formatted = $this->getFormatter()->format($record);

        $rowNum = $this->schema->createQuery()->insert(TaskLogsTable::NAME, [
            'id' => new Ulid(Ulid::generate($record->datetime)),
            'task_id' => $taskId,
            'level' => $record->level,
            'message' => $record->formatted['message'],
            'context' => $record->formatted['context'],
            'extra' => $record->formatted['extra'],
        ])->executeStatement();

        if ($rowNum !== 1) {
            return false;
        }

        return false === $this->bubble;
    }
}
