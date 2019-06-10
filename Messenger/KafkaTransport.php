<?php

namespace Koco\Kafka\Messenger;

use Exception;
use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use RdKafka\KafkaConsumer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransport implements TransportInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var SerializerInterface */
    private $serializer;

    /** @var KafkaConf */
    private $kafkaConf;

    /** @var KafkaConsumer */
    private $consumer;

    /** @var string */
    private $topicName;

    /** @var int */
    private $timeoutMs;

    /** @var bool */
    private $commitAsync;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        KafkaConf $kafkaConf,
        string $topicName,
        int $timeoutMs,
        bool $commitAsync
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->kafkaConf = $kafkaConf;
        $this->topicName = $topicName;
        $this->timeoutMs = $timeoutMs;
        $this->commitAsync = $commitAsync;
    }

    public function get(): iterable
    {
        $consumer = $this->getConsumer();

        $consumer->subscribe([$this->topicName]);

        $this->logger->info('Partition assignment...');

        while (true) {
            $message = $consumer->consume($this->timeoutMs);
            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->logger->info(sprintf('Kafka: Message %s %s %s received ', $message->topic_name, $message->partition, $message->offset));

                    /** @var Envelope $envelope */
                    $envelope = $this->serializer->decode(array(
                        'body' => json_decode($message->payload, true)['body']
                    ));

                    if ($envelope) {
                        $envelope = $envelope->with(new KafkaMessageStamp($message));
                    }

                    return array($envelope);

                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    $this->logger->info('Kafka: Partition EOF reached. Waiting for next message ...');
                    break;
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    $this->logger->debug('Kafka: Consumer timeout.');
                    break;
                default:
                    throw new \Exception($message->errstr(), $message->err);
                    break;
            }
        }
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
        throw new Exception('Kafka reject');
    }

    /**
     * Sends the given envelope.
     *
     * The sender can read different stamps for transport configuration,
     * like delivery delay.
     *
     * If applicable, the returned Envelope should contain a TransportMessageIdStamp.
     */
    public function send(Envelope $envelope): Envelope
    {
        throw new Exception('Kafka send');
    }

    private function getConsumer(): KafkaConsumer
    {
        if($this->consumer) {
            return $this->consumer;
        }

        $this->consumer = new KafkaConsumer($this->kafkaConf);

        return $this->consumer;
    }
}