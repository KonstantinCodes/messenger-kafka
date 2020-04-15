<?php

namespace Koco\Kafka\Tests\Unit\Messenger;

use Koco\Kafka\Messenger\KafkaMessageDecoder;
use Koco\Kafka\Messenger\KafkaMessageStamp;
use Koco\Kafka\Messenger\KafkaTransport;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer as KafkaProducer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class KafkaTransportTest extends TestCase
{
    /** @var MockObject|LoggerInterface */
    private $mockLogger;

    /** @var MockObject|SerializerInterface */
    private $mockSerializer;

    /** @var MockObject|KafkaMessageDecoder */
    private $mockDecoder;

    /** @var MockObject|KafkaConsumer */
    private $mockRdKafkaConsumer;

    /** @var MockObject|KafkaProducer */
    private $mockRdKafkaProducer;

    /** @var MockObject|RdKafkaFactory */
    private $mockRdKafkaFactory;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        $this->mockSerializer = $this->createMock(SerializerInterface::class);

        $this->mockDecoder = $this->createMock(KafkaMessageDecoder::class);

        // RdKafka
        $this->mockRdKafkaFactory = $this->createMock(RdKafkaFactory::class);

        $this->mockRdKafkaConsumer = $this->createMock(KafkaConsumer::class);
        $this->mockRdKafkaFactory
            ->method('createConsumer')
            ->willReturn($this->mockRdKafkaConsumer);

        $this->mockRdKafkaProducer = $this->createMock(KafkaProducer::class);
        $this->mockRdKafkaFactory
            ->method('createProducer')
            ->willReturn($this->mockRdKafkaProducer);
    }

    public function testConstruct()
    {
        $transport = new KafkaTransport(
            $this->mockLogger,
            $this->mockSerializer,
            $this->mockDecoder,
            new RdKafkaFactory(),
            new KafkaConf(),
            'test',
            10000,
            10000,
            false
        );

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testGet()
    {
        $this->mockRdKafkaConsumer
            ->method('subscribe')
            ->willReturn(true);

        $testMessage = new Message;
        $testMessage->err = RD_KAFKA_RESP_ERR_NO_ERROR;
        $testMessage->topic_name = 'test';
        $testMessage->partition = 0;
        $testMessage->payload = json_encode([
            'body' => 'test',
            'headers' => 'asdf'
        ]);
        $testMessage->offset = 0;
        $testMessage->timestamp = 1586861356;

        $this->mockRdKafkaConsumer
            ->method('consume')
            ->willReturn($testMessage);

        $this->mockDecoder->expects($this->once())
            ->method('decode')
            ->with($testMessage)
            ->willReturn(['body' => 'test', 'headers' => 'asdf']);

        $this->mockSerializer->expects($this->once())
            ->method('decode')
            ->with(['body' => 'test', 'headers' => 'asdf'])
            ->willReturn(new Envelope(new TestMessage()));

        $transport = new KafkaTransport(
            $this->mockLogger,
            $this->mockSerializer,
            $this->mockDecoder,
            $this->mockRdKafkaFactory,
            new KafkaConf(),
            'test',
            10000,
            10000,
            false
        );

        $receivedMessages = $transport->get();
        $this->assertArrayHasKey(0, $receivedMessages);

        /** @var Envelope $receivedMessage */
        $receivedMessage = $receivedMessages[0];
        $this->assertInstanceOf(Envelope::class, $receivedMessage);
        $this->assertInstanceOf(TestMessage::class, $receivedMessage->getMessage());

        $stamps = $receivedMessage->all();
        $this->assertCount(1, $stamps);
        $this->assertArrayHasKey(KafkaMessageStamp::class, $stamps);

        $kafkaMessageStamps = $stamps[KafkaMessageStamp::class];
        $this->assertCount(1, $kafkaMessageStamps);

        /** @var KafkaMessageStamp $kafkaMessageStamp */
        $kafkaMessageStamp = $kafkaMessageStamps[0];
        $this->assertEquals($testMessage, $kafkaMessageStamp->getMessage());
    }
}