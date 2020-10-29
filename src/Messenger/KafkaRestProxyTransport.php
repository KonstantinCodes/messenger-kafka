<?php

namespace Koco\Kafka\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaRestProxyTransport implements TransportInterface, MessageCountAwareInterface
{
    private $serializer;
    private $receiver;
    private $sender;

    public function __construct(SerializerInterface $serializer = null)
    {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        throw new \Exception('Not implemented!');
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        throw new \Exception('Not implemented!');
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        throw new \Exception('Not implemented!');
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        return ($this->sender ?? $this->getSender())->send($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageCount(): int
    {
        throw new \Exception('Not implemented!');
    }

    private function getSender(): KafkaRestProxySender
    {
        return $this->sender = new KafkaRestProxySender($this->connection, $this->serializer);
    }
}