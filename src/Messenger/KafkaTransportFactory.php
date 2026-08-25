<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use function explode;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use const RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS;
use const RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS;
use RdKafka\Conf as KafkaConf;
use RdKafka\KafkaConsumer;
use RdKafka\TopicPartition;
use function sprintf;
use function str_replace;
use function strpos;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransportFactory implements TransportFactoryInterface
{
    private const DSN_PROTOCOLS = [
        self::DSN_PROTOCOL_KAFKA,
        self::DSN_PROTOCOL_KAFKA_SSL,
    ];
    private const DSN_PROTOCOL_KAFKA = 'kafka://';
    private const DSN_PROTOCOL_KAFKA_SSL = 'kafka+ssl://';

    private LoggerInterface $logger;
    private RdKafkaFactory $kafkaFactory;

    public function __construct(
        RdKafkaFactory $kafkaFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->kafkaFactory = $kafkaFactory;
    }

    public function supports(string $dsn, array $options): bool
    {
        foreach (self::DSN_PROTOCOLS as $protocol) {
            if (0 === strpos($dsn, $protocol)) {
                return true;
            }
        }

        return false;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $brokers = implode(',', $this->stripProtocol($dsn));
        $extraConf = array_merge($options['topic_conf'] ?? [], $options['kafka_conf'] ?? []);

        // Sender and receiver get SEPARATE Conf instances on purpose.
        //
        // Previously a single Conf was built here, had the rebalance callback set
        // on it and was then handed to both. `rebalance_cb` is a consumer-only
        // property, so every producer created from that Conf made librdkafka emit
        //
        //   CONFWARN: Configuration property rebalance_cb is a consumer property
        //   and will be ignored by this producer instance
        //
        // on each instantiation. Harmless, but for a producer that publishes on
        // every HTTP request it means one warning line per request in the logs.
        $senderConf = $this->createConf($brokers, $extraConf);

        $receiverConf = $this->createConf($brokers, $extraConf);
        // Set a rebalance callback to log partition assignments (optional)
        $receiverConf->setRebalanceCb($this->createRebalanceCb($this->logger));

        return new KafkaTransport(
            $this->logger,
            $serializer,
            $this->kafkaFactory,
            new KafkaSenderProperties(
                $senderConf,
                $options['topic']['name'],
                $options['flushTimeout'] ?? 10000,
                $options['flushRetries'] ?? 0
            ),
            new KafkaReceiverProperties(
                $receiverConf,
                $options['topic']['name'],
                $options['receiveTimeout'] ?? 10000,
                $options['commitAsync'] ?? false
            )
        );
    }

    /**
     * @param array<string, mixed> $extraConf
     */
    private function createConf(string $brokers, array $extraConf): KafkaConf
    {
        $conf = new KafkaConf();
        $conf->set('metadata.broker.list', $brokers);

        foreach ($extraConf as $option => $value) {
            $conf->set($option, $value);
        }

        return $conf;
    }

    private function stripProtocol(string $dsn): array
    {
        $brokers = [];
        foreach (explode(',', $dsn) as $currentBroker) {
            foreach (self::DSN_PROTOCOLS as $protocol) {
                $currentBroker = str_replace($protocol, '', $currentBroker);
            }
            $brokers[] = $currentBroker;
        }

        return $brokers;
    }

    private function createRebalanceCb(LoggerInterface $logger): \Closure
    {
        return function (KafkaConsumer $kafka, int $err, ?array $topicPartitions = null) use ($logger) {
            /** @var TopicPartition[] $topicPartitions */
            $topicPartitions = $topicPartitions ?? [];

            switch ($err) {
                case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    foreach ($topicPartitions as $topicPartition) {
                        $logger->info(sprintf('Assign: %s %s %s', $topicPartition->getTopic(), $topicPartition->getPartition(), $topicPartition->getOffset()));
                    }
                    $kafka->assign($topicPartitions);
                    break;

                case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    foreach ($topicPartitions as $topicPartition) {
                        $logger->info(sprintf('Assign: %s %s %s', $topicPartition->getTopic(), $topicPartition->getPartition(), $topicPartition->getOffset()));
                    }
                    $kafka->assign(null);
                    break;

                default:
                    throw new \RuntimeException((string) $err);
            }
        };
    }
}
