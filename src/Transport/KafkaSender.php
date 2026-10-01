<?php

declare(strict_types=1);

namespace Koco\Kafka\Transport;

use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use RdKafka\Producer as KafkaProducer;
use RdKafka\ProducerTopic;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
class KafkaSender implements SenderInterface
{
    private LoggerInterface $logger;
    private SerializerInterface $serializer;
    private RdKafkaFactory $rdKafkaFactory;
    private KafkaConf $conf;
    private array $properties;

    /** @var KafkaProducer */
    private $producer;

    /** @var ProducerTopic */
    private $topic;

    public function __construct(LoggerInterface $logger, SerializerInterface $serializer, RdKafkaFactory $rdKafkaFactory, KafkaConf $conf, array $properties)
    {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->rdKafkaFactory = $rdKafkaFactory;
        $this->conf = $conf;
        $this->properties = $properties;
    }

    public function send(Envelope $envelope): Envelope
    {
        $topic = $this->getTopic($this->properties['topic_name']);

        $payload = $this->serializer->encode($envelope);

        $topic->producev(
            RD_KAFKA_PARTITION_UA,
            0,
            $payload['body'],
            $payload['key'] ?? null,
            $payload['headers'] ?? null,
            $payload['timestamp_ms'] ?? null,
        );

        $this->getProducer()->poll(0);
        $this->flush();

        return $envelope;
    }

    public function flush(): void
    {
        $code = RD_KAFKA_RESP_ERR_NO_ERROR;
        for ($flushTry = 0; $flushTry <= $this->properties['flush_retries']; ++$flushTry) {
            $code = $this->getProducer()->flush($this->properties['flush_timeout']);
            if (RD_KAFKA_RESP_ERR_NO_ERROR === $code) {
                break;
            }
            $this->logger->info(\sprintf('Kafka flush #%s didn\'t succeed.', $flushTry));
        }

        if (RD_KAFKA_RESP_ERR_NO_ERROR !== $code) {
            throw new TransportException('Kafka producer response error: ' . $code, $code);
        }
    }

    private function getProducer(): KafkaProducer
    {
        return $this->producer ?? $this->producer = $this->rdKafkaFactory->createProducer($this->conf);
    }

    private function getTopic(string $topicName): ProducerTopic
    {
        return $this->topic ?? $this->topic = $this->getProducer()->newTopic($topicName);
    }
}
