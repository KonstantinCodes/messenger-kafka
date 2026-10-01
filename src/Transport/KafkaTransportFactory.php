<?php

declare(strict_types=1);

namespace Koco\Kafka\Transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransportFactory implements TransportFactoryInterface
{
    private LoggerInterface $logger;
    private RdKafkaFactory $rdKafkaFactory;

    public function __construct(?LoggerInterface $logger = null, ?RdKafkaFactory $rdKafkaFactory = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->rdKafkaFactory = $rdKafkaFactory ?? new RdKafkaFactory();
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'kafka://') || 0 === strpos($dsn, 'kafka+ssl://');
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        if (!$this->supports($dsn, $options)) {
            throw new InvalidArgumentException('Kafka DSNs must start with kafka:// or kafka+ssl://.');
        }
        // FrameworkBundle adds this internal option to every transport.
        unset($options['transport_name']);
        $parts = explode('?', $dsn, 2);
        $brokers = [];
        foreach (explode(',', $parts[0]) as $broker) {
            $broker = preg_replace('{^kafka(?:\+ssl)?://}', '', trim($broker));
            $url = parse_url('kafka://' . $broker);
            if (false === $url || empty($url['host']) || isset($url['path']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || (isset($url['port']) && $url['port'] < 1)) {
                throw new InvalidArgumentException(\sprintf('Invalid Kafka broker in DSN "%s".', $dsn));
            }
            $brokers[] = $url['host'] . ':' . ($url['port'] ?? 9092);
        }
        parse_str($parts[1] ?? '', $query);
        // Validate both sources; explicit options override query values, including topic lists.
        KafkaOptions::resolve($query);
        KafkaOptions::resolve($options);
        $merged = [];
        foreach (['producer', 'consumer'] as $section) {
            $merged[$section] = array_replace($query[$section] ?? [], $options[$section] ?? []);
            $merged[$section]['conf'] = array_replace($query[$section]['conf'] ?? [], $options[$section]['conf'] ?? []);
        }
        $merged['conf'] = array_replace($query['conf'] ?? [], $options['conf'] ?? []);
        $merged['conf']['metadata.broker.list'] = implode(',', $brokers);

        return new KafkaTransport($this->logger, $serializer, $this->rdKafkaFactory, $merged);
    }
}
