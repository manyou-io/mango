<?php

declare(strict_types=1);

namespace Mango\Scheduler\Transport;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

use function get_debug_type;
use function parse_str;
use function parse_url;
use function sprintf;

#[AsDecorator('messenger.transport.doctrine.factory')]
class DoctrineSchedulerTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private TransportFactoryInterface $inner,
        #[Autowire(service: 'doctrine')]
        private ConnectionRegistry $registry,
        private LoggerInterface $logger,
    ) {
    }

    private function buildConfiguration(string $dsn, array $options): array
    {
        $components = parse_url($dsn);

        $query = [];
        if (isset($components['query'])) {
            parse_str($components['query'], $query);
        }

        $configuration  = ['connection' => $components['host']];
        $configuration += $query + $options + [
            'table_name' => 'messenger_messages',
            'queue_name' => 'default',
            'redeliver_timeout' => 3600,
            'auto_setup' => true,
        ];

        return $configuration;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $transport = $this->inner->createTransport($dsn, $options, $serializer);

        if (! $transport instanceof DoctrineTransport) {
            throw new TransportException(sprintf('Expected "%s", got "%s".', DoctrineTransport::class, get_debug_type($transport)));
        }

        $configuration = $this->buildConfiguration($dsn, $options);

        /** @var DBALConnection */
        $driverConnection = $this->registry->getConnection($configuration['connection']);

        return new DoctrineSchedulerTransport($transport, $driverConnection, $configuration, $this->logger);
    }

    public function supports(string $dsn, array $options): bool
    {
        return $this->inner->supports($dsn, $options);
    }
}
