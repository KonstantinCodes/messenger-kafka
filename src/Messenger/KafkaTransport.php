<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use Koco\Kafka\RdKafka\RdKafkaFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransport implements TransportInterface, MessageCountAwareInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var SerializerInterface */
    private $serializer;

    /** @var RdKafkaFactory */
    private $rdKafkaFactory;

    /** @var KafkaSenderProperties */
    private $kafkaSenderProperties;

    /** @var KafkaReceiverProperties */
    private $kafkaReceiverProperties;

    /** @var KafkaSender */
    private $sender;

    /** @var KafkaReceiver */
    private $receiver;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        RdKafkaFactory $rdKafkaFactory,
        KafkaSenderProperties $kafkaSenderProperties,
        KafkaReceiverProperties $kafkaReceiverProperties
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->rdKafkaFactory = $rdKafkaFactory;
        $this->kafkaSenderProperties = $kafkaSenderProperties;
        $this->kafkaReceiverProperties = $kafkaReceiverProperties;
    }

    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    private function getSender(): KafkaSender
    {
        return $this->sender ?? $this->sender = new KafkaSender(
            $this->logger,
            $this->serializer,
            $this->rdKafkaFactory,
            $this->kafkaSenderProperties
        );
    }

    private function getReceiver(): KafkaReceiver
    {
        return $this->receiver ?? $this->receiver = new KafkaReceiver(
            $this->logger,
            $this->serializer,
            $this->rdKafkaFactory,
            $this->kafkaReceiverProperties
        );
    }

    public function getMessageCount(): int
    {
        $consumer = $this->rdKafkaFactory->createConsumer($this->kafkaReceiverProperties->getKafkaConf());
        $metadata = $consumer->getMetadata(false, null, 60 * 1000);
        $topics = $metadata->getTopics();
        $topicName = $this->kafkaReceiverProperties->getTopicName();

        $lag = 0;
        foreach ($topics as $topic) {
            if ($topic->getTopic() !== $topicName) {
                continue;
            }

            foreach ($topic->getPartitions() as $partition) {
                $partitionId = $partition->getId();

                $lowOffset = $highOffset = 0;
                $consumer->queryWatermarkOffsets($topicName, $partitionId, $lowOffset, $highOffset, 1000);

                $committedOffsets = $consumer->getCommittedOffsets(
                    [new \RdKafka\TopicPartition($topicName, $partitionId)],
                    1000
                );

                $committedOffset = $committedOffsets[0]->getOffset();
                if ($committedOffset >= 0) {
                    $lag += $highOffset - $committedOffset;
                }
            }
        }

        return $lag;
    }
}
