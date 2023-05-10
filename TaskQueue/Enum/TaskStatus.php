<?php

declare(strict_types=1);

namespace Manyou\Mango\TaskQueue\Enum;

enum TaskStatus: string
{
    case QUEUEING   = 'queueing';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
    case CANCELLED  = 'cancelled';
}
