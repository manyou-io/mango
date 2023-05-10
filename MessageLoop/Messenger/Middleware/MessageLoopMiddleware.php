<?php

declare(strict_types=1);

namespace Manyou\Mango\MessageLoop\Messenger\Middleware;

use Manyou\Mango\MessageLoop\MessageLoopRepository;
use Manyou\Mango\MessageLoop\Messenger\Stamp\MessageLoopStamp;
use Manyou\Mango\MessageLoop\Messenger\Stamp\ResetMessageLoopStamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Throwable;

class MessageLoopMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageLoopRepository $repository,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
    ) {
    }

    private function onCreate(Envelope $envelope, StackInterface $stack, ResetMessageLoopStamp $stamp): Envelope
    {
        $this->logger->notice('Resetting message loop {key} to {loopId}.', [
            'key' => $stamp->key,
            'loopId' => $stamp->loopId,
        ]);

        $this->repository->resetLoop($stamp->key, $stamp->loopId);

        $envelope = $envelope
            ->withoutStampsOfType(ResetMessageLoopStamp::class)
            ->with($stamp->toMessageLoopStamp());

        return $stack->next()->handle($envelope, $stack);
    }

    private function onConsume(Envelope $envelope, StackInterface $stack, MessageLoopStamp $stamp): Envelope
    {
        if (! $this->repository->isValidLoop($stamp->key, $stamp->loopId)) {
            $this->logger->notice('Skipping invalid message loop {key} of ID {loopId}.', [
                'key' => $stamp->key,
                'loopId' => $stamp->loopId,
            ]);

            return $envelope;
        }

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (Throwable $e) {
            $this->logger->error('Message loop failed: ' . $e->getMessage(), [
                'key' => $stamp->key,
                'loopId' => $stamp->loopId,
                'exception' => $e,
            ]);

            return $envelope;
        }

        $this->messageBus->dispatch(
            $envelope->getMessage(),
            [$stamp, new DispatchAfterCurrentBusStamp(), new DelayStamp($stamp->delay)],
        );

        return $envelope;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var ResetMessageLoopStamp|null */
        if (null !== $stamp = $envelope->last(ResetMessageLoopStamp::class)) {
            return $this->onCreate($envelope, $stack, $stamp);
        }

        /** @var MessageLoopStamp|null */
        if (null !== $stamp = $envelope->last(MessageLoopStamp::class)) {
            if (null !== $envelope->last(ReceivedStamp::class)) {
                return $this->onConsume($envelope, $stack, $stamp);
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
