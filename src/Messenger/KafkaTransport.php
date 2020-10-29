<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use Koco\Kafka\RdKafka\RdKafkaFactory;
use Psr\Log\LoggerInterface;
use const RD_KAFKA_PARTITION_UA;
use RdKafka\Conf as KafkaConf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer as KafkaProducer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransport implements TransportInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var SerializerInterface */
    private $serializer;

    /** @var RdKafkaFactory */
    private $rdKafkaFactory;

    /** @var KafkaConf */
    private $kafkaConf;

    /** @var KafkaConsumer */
    private $consumer;

    /** @var KafkaProducer */
    private $producer;

    /** @var string */
    private $topicName;

    /** @var int */
    private $flushTimeoutMs;

    /** @var int */
    private $receiveTimeoutMs;

    /** @var bool */
    private $commitAsync;

    /** @var bool */
    private $subscribed;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        RdKafkaFactory $rdKafkaFactory,
        KafkaConf $kafkaConf,
        string $topicName,
        int $flushTimeoutMs,
        int $receiveTimeoutMs,
        bool $commitAsync
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->rdKafkaFactory = $rdKafkaFactory;
        $this->kafkaConf = $kafkaConf;
        $this->topicName = $topicName;
        $this->flushTimeoutMs = $flushTimeoutMs;
        $this->receiveTimeoutMs = $receiveTimeoutMs;
        $this->commitAsync = $commitAsync;

        $this->subscribed = false;
    }

    public function get(): iterable
    {
        $message = $this->getSubscribedConsumer()->consume($this->receiveTimeoutMs);

        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                $this->logger->info(sprintf('Kafka: Message %s %s %s received ', $message->topic_name, $message->partition, $message->offset));

                $envelope = $this->serializer->decode([
                    'body' => $message->payload,
                    'headers' => $message->headers,
                ]);

                return [$envelope->with(new KafkaMessageStamp($message))];
            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                $this->logger->info('Kafka: Partition EOF reached. Waiting for next message ...');
                break;
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                $this->logger->debug('Kafka: Consumer timeout.');
                break;
            case RD_KAFKA_RESP_ERR__TRANSPORT:
                $this->logger->debug('Kafka: Broker transport failure.');
                break;
            default:
                throw new \Exception($message->errstr(), $message->err);
        }

        return [];
    }

    public function getSubscribedConsumer(): KafkaConsumer
    {
        $consumer = $this->getConsumer();

        if (false === $this->subscribed) {
            $consumer->subscribe([$this->topicName]);
            $this->logger->info('Partition assignment...');

            $this->subscribed = true;
        }

        return $consumer;
    }

    public function ack(Envelope $envelope): void
    {
        $consumer = $this->getConsumer();

        /** @var KafkaMessageStamp $transportStamp */
        $transportStamp = $envelope->last(KafkaMessageStamp::class);
        $message = $transportStamp->getMessage();

        if ($this->commitAsync) {
            $consumer->commitAsync($message);
        } else {
            $consumer->commit($message);
        }

        $this->logger->info(sprintf('Message %s %s %s ack successful.', $message->topic_name, $message->partition, $message->offset));
    }

    public function reject(Envelope $envelope): void
    {
        // Do nothing. auto commit should be set to false!
    }

    public function send(Envelope $envelope): Envelope
    {
        $producer = $this->getProducer();
        $topic = $producer->newTopic($this->topicName);

        $payload = $this->serializer->encode($envelope);

        $topic->producev(RD_KAFKA_PARTITION_UA, 0, $payload['body'], $payload['key'] ?? null, $payload['headers'] ?? null);

        $producer->flush($this->flushTimeoutMs);

        return $envelope;
    }

    private function getConsumer(): KafkaConsumer
    {
        if ($this->consumer !== null) {
            return $this->consumer;
        }

        $this->consumer = $this->rdKafkaFactory->createConsumer($this->kafkaConf);

        return $this->consumer;
    }

    private function getProducer(): KafkaProducer
    {
        if ($this->producer !== null) {
            return $this->producer;
        }

        $this->producer = $this->rdKafkaFactory->createProducer($this->kafkaConf);

        return $this->producer;
    }
}
