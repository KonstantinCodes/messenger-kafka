<?php

namespace Koco\Kafka\Transport;

use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
class KafkaTransport implements TransportInterface
{
    private LoggerInterface $logger;
    private SerializerInterface $serializer;
    private RdKafkaFactory $rdKafkaFactory;
    private array $options;

    /** @var KafkaSender */
    private $sender;

    /** @var KafkaReceiver */
    private $receiver;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        RdKafkaFactory $rdKafkaFactory,
        array $options
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->rdKafkaFactory = $rdKafkaFactory;
        $this->options = $options;
    }

    /**
     * @return Envelope[]
     *
     * @psalm-return array{0?: Envelope}
     */
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
            $this->buildConf($this->options['conf'], $this->options['producer']['conf'] ?? []),
            $this->options['producer'] ?? []
        );
    }

    private function getReceiver(): KafkaReceiver
    {
        return $this->receiver ?? $this->receiver = new KafkaReceiver(
            $this->logger,
            $this->serializer,
            $this->rdKafkaFactory,
            $this->buildConf($this->options['conf'], $this->options['consumer']['conf'] ?? []),
            $this->options['consumer'] ?? []
        );
    }

    private function buildConf(array $baseConf, array $specificConf): KafkaConf
    {
        $conf = new KafkaConf();
        $confOptions = array_merge($baseConf, $specificConf);

        foreach ($confOptions as $option => $value) {
            $conf->set($option, $value);
        }

        return $conf;
    }
}
