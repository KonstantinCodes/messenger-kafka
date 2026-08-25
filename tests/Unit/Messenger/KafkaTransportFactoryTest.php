<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Unit\Messenger;

use Koco\Kafka\Messenger\KafkaTransportFactory;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransportFactoryTest extends TestCase
{
    /** @var KafkaTransportFactory */
    private $factory;

    /** @var SerializerInterface */
    private $serializerMock;

    protected function setUp(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $this->factory = new KafkaTransportFactory(new RdKafkaFactory(), $logger);

        $this->serializerMock = $this->createMock(SerializerInterface::class);
    }

    public function testSupports()
    {
        static::assertTrue($this->factory->supports('kafka://my-local-kafka:9092', []));
        static::assertTrue($this->factory->supports('kafka+ssl://my-staging-kafka:9093', []));
        static::assertTrue($this->factory->supports('kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093', []));
    }

    #[\PHPUnit\Framework\Attributes\Group('legacy')]
    public function testCreateTransport()
    {
        $transport = $this->factory->createTransport(
            'kafka://my-local-kafka:9092',
            [
                'flushTimeout' => 10000,
                'topic' => [
                    'name' => 'kafka',
                ],
                'kafka_config' => [
                ],
            ],
            $this->serializerMock
        );

        static::assertInstanceOf(TransportInterface::class, $transport);
    }

    /**
     * Sender and receiver must not share a Conf instance: the rebalance callback
     * is a consumer-only property, and setting it on the producer's Conf makes
     * librdkafka log a CONFWARN on every producer instantiation.
     */
    #[\PHPUnit\Framework\Attributes\Group('legacy')]
    public function testSenderAndReceiverGetSeparateConf()
    {
        $transport = $this->factory->createTransport(
            'kafka://my-local-kafka:9092',
            [
                'topic' => ['name' => 'kafka'],
                'kafka_conf' => ['group.id' => 'test-group'],
            ],
            $this->serializerMock
        );

        $senderConf = $this->readProperty($transport, 'kafkaSenderProperties')->getKafkaConf();
        $receiverConf = $this->readProperty($transport, 'kafkaReceiverProperties')->getKafkaConf();

        static::assertNotSame($senderConf, $receiverConf, 'producer and consumer must own their Conf');
        static::assertSame(
            $senderConf->dump()['metadata.broker.list'],
            $receiverConf->dump()['metadata.broker.list'],
            'both must still be configured from the same DSN'
        );
        static::assertSame('test-group', $receiverConf->dump()['group.id'], 'kafka_conf must still be applied');
        static::assertSame('test-group', $senderConf->dump()['group.id']);
    }

    private function readProperty(object $object, string $property)
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
