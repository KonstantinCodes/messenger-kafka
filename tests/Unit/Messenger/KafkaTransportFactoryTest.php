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
    /** @var LoggerInterface */
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
        self::assertTrue($this->factory->supports('kafka://my-local-kafka:9092', []));
        self::assertTrue($this->factory->supports('kafka+ssl://my-staging-kafka:9093', []));
        self::assertTrue($this->factory->supports('kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093', []));
    }

    /**
     * @group legacy
     */
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

        self::assertInstanceOf(TransportInterface::class, $transport);
    }
}
