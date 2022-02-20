<?php

namespace Koco\Kafka\Transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
class KafkaTransportFactory implements TransportFactoryInterface
{
    /**
     * @var LoggerInterface|NullLogger
     */
    private $logger;

    public function __construct(?LoggerInterface $logger)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'kafka://');
    }

    /**
     * @return KafkaTransport
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Kafka DSN "%s" is invalid.', $dsn));
        }

        parse_str($parsedUrl['query'] ?? '', $parsedQuery);
        $options = array_replace($parsedQuery, $options);
        $options['conf']['metadata.broker.list'] = $parsedUrl['host'] . ':' . ($parsedUrl['port'] ?? '9092');

        return new KafkaTransport($this->logger, $serializer, new RdKafkaFactory(), $options);
    }
}
