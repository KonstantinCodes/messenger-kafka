<?php

namespace Koco\Kafka\Tests\Unit\Messenger;

use Koco\Kafka\Messenger\KafkaMessageDecoderInterface;
use Koco\Kafka\Messenger\KafkaMessageJsonDecoder;
use Koco\Kafka\Messenger\KafkaTransport;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransportTest extends TestCase
{
    /** @var LoggerInterface */
    private $mockLogger;

    /** @var SerializerInterface */
    private $mockSerializer;

    /** @var KafkaMessageDecoderInterface */
    private $mockDecoder;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockSerializer = $this->createMock(SerializerInterface::class);
        $this->mockDecoder = $this->createMock(KafkaMessageDecoderInterface::class);
    }

    public function testConstruct()
    {
        $transport = new KafkaTransport(
            $this->mockLogger,
            $this->mockSerializer,
            $this->mockDecoder,
            new KafkaConf(),
            'test',
            10000,
            10000,
            false
        );

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }
}