<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use Koco\Kafka\RdKafka\RdKafkaFactory;
use Psr\Log\LoggerInterface;
use RdKafka\Producer as KafkaProducer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaSender implements SenderInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var SerializerInterface */
    private $serializer;

    /** @var RdKafkaFactory */
    private $rdKafkaFactory;

    /** @var KafkaSenderProperties */
    private $properties;

    /** @var KafkaProducer */
    private $producer;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        RdKafkaFactory $rdKafkaFactory,
        KafkaSenderProperties $properties
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->rdKafkaFactory = $rdKafkaFactory;
        $this->properties = $properties;
    }

    public function send(Envelope $envelope): Envelope
    {
        $producer = $this->getProducer();
        $topic = $producer->newTopic($this->properties->getTopicName());

        $payload = $this->serializer->encode($envelope);

        $topic->producev(
            RD_KAFKA_PARTITION_UA,
            0,
            $payload['body'],
            $payload['key'] ?? null,
            $payload['headers'] ?? null,
            $payload['timestamp_ms'] ?? null
        );

        for ($flushRetries = 0; $flushRetries < $this->properties->getFlushRetries() + 1; ++$flushRetries) {
            $code = $producer->flush($this->properties->getFlushTimeoutMs());
            if ($code === RD_KAFKA_RESP_ERR_NO_ERROR) {
                $this->logger->info(sprintf('Kafka message sent%s', \array_key_exists('key', $payload) ? ' with key ' . $payload['key'] : ''));
                break;
            }
        }

        if ($code !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new TransportException('Kafka producer response error: ' . $code, $code);
        }

        return $envelope;
    }

    private function getProducer(): KafkaProducer
    {
        return $this->producer ?? $this->producer = $this->rdKafkaFactory->createProducer($this->properties->getKafkaConf());
    }
}
