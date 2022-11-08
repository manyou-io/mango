<?php

declare(strict_types=1);

namespace Manyou\Mango\Operation\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Ulid;

class OperationStamp implements StampInterface
{
    public function __construct(private Ulid $id)
    {
    }

    public function getId(): Ulid
    {
        return $this->id;
    }
}
