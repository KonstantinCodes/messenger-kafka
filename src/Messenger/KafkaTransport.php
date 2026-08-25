<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use Koco\Kafka\RdKafka\RdKafkaFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransport implements TransportInterface
{
    private LoggerInterface $logger;
    private SerializerInterface $serializer;
    private RdKafkaFactory $rdKafkaFactory;
    private KafkaSenderProperties $kafkaSenderProperties;
    private KafkaReceiverProperties $kafkaReceiverProperties;
    private ?KafkaSender $sender = null;
    private ?KafkaReceiver $receiver = null;

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
}
