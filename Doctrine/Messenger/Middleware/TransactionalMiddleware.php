<?php

declare(strict_types=1);

namespace Manyou\Mango\Doctrine\Messenger\Middleware;

use Manyou\Mango\Doctrine\SchemaProvider;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class TransactionalMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SchemaProvider $schema,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $this->schema->getConnection()->transactional(static function () use ($envelope, $stack) {
            return $stack->next()->handle($envelope, $stack);
        });
    }
}
