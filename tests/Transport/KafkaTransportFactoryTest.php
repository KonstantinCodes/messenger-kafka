<?php

namespace Koco\Kafka\Tests\Transport;

use Koco\Kafka\Transport\KafkaTransportFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 *
 * @requires extension rdkafka
 */
class KafkaTransportFactoryTest extends TestCase
{
    /** @var KafkaTransportFactory */
    private $factory;

    /** @var SerializerInterface */
    private $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new KafkaTransportFactory(new NullLogger());
        $this->serializer = $this->createMock(SerializerInterface::class);
    }

    public function testSupports(): void
    {
        static::assertTrue($this->factory->supports('kafka://my-local-kafka:9092', []));
        static::assertTrue($this->factory->supports('kafka://prod-kafka-01:9093,prod-kafka-01:9093,prod-kafka-01:9093', []));
    }

    public function testCreateTransport(): void
    {
        $transport = $this->factory->createTransport(
            'kafka://my-local-kafka:9092',
            [
                'conf' => [],
                'consumer' => [
                    'topics' => [
                        'test',
                    ],
                    'receive_timeout' => 10000,
                    'conf' => [],
                ],
            ],
            $this->serializer
        );

        static::assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testCreateTransportFromDsn(): void
    {
        $transport = $this->factory->createTransport(
            'kafka://kafka1,kafka2:9092?consumer[topics][0]=test&consumer[receive_timeout]=10000',
            [],
            $this->serializer
        );

        static::assertInstanceOf(TransportInterface::class, $transport);
    }
}
