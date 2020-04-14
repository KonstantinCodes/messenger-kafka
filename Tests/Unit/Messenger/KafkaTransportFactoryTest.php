<?php

namespace Koco\Kafka\Tests\Unit\Messenger;

use Koco\Kafka\Messenger\KafkaTransportFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransportFactoryTest extends TestCase
{
    /** @var LoggerInterface */
    private $factory;

    /** @var SerializerInterface */
    private $serializerMock;

    protected function setUp(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $this->factory = new KafkaTransportFactory($logger);

        $this->serializerMock = $this->createMock(SerializerInterface::class);
    }

    public function testSupports()
    {
        $this->assertTrue($this->factory->supports('kafka://my-local-kafka:9092', []));
        $this->assertTrue($this->factory->supports('kafka+ssl://my-staging-kafka:9093', []));
        $this->assertTrue($this->factory->supports('kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093', []));
    }

    /**
     * @group legacy
     * @expectedDeprecation Unsilenced deprecation: Function RdKafka\Conf::setDefaultTopicConf() is deprecated
     */
    public function testCreateTransport()
    {
        $transport = $this->factory->createTransport(
            'kafka://my-local-kafka:9092',
            [
                'flushTimeout' => 10000,
                'topic' => [
                    'name' => 'kafka'
                ],
                'kafka_config' => [

                ]
            ],
            $this->serializerMock
        );

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }
}