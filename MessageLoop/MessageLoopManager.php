<?php

declare(strict_types=1);

namespace Manyou\Mango\MessageLoop;

use Manyou\Mango\MessageLoop\Messenger\Stamp\ResetMessageLoopStamp;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class MessageLoopManager
{
    /** @param MessageLoopInterface[] $loopIterator */
    public function __construct(
        #[TaggedIterator('mango.message_loop', 'index')]
        private iterable $loopIterator,
        #[TaggedLocator('mango.message_loop', 'index')]
        private ContainerInterface $loopLocator,
        private MessageBusInterface $messageBus,
        private MessageLoopRepository $repository,
    ) {
    }

    public function resetAll(): void
    {
        foreach ($this->loopIterator as $key => $messageLoop) {
            $this->resetLoop($key, $messageLoop);
        }
    }

    public function reset(string $key): void
    {
        $this->resetLoop($key, $this->loopLocator->get($key));
    }

    private function resetLoop(string $key, MessageLoopInterface $messageLoop): void
    {
        $this->messageBus->dispatch($messageLoop->getMessage(), [
            new ResetMessageLoopStamp($key, new Ulid(), $messageLoop->getDelay()),
        ]);
    }

    public function delete(string $key): void
    {
        $this->repository->deleteLoop($key);
    }
}
