<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use Psr\Log\LoggerInterface;
use function strpos;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaRestProxyTransportFactory implements TransportFactoryInterface
{
    private const DSN_PROTOCOL_KAFKA = 'kafka+rest://';

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, static::DSN_PROTOCOL_KAFKA);
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new KafkaRestProxyTransport($serializer);
    }
}
