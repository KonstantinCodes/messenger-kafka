<?php

namespace Koco\Kafka\Tests\Transport;

use Koco\Kafka\Transport\KafkaMessageStamp;
use Koco\Kafka\Transport\KafkaTransport;
use Koco\Kafka\Transport\RdKafkaFactory;
use Koco\Kafka\Tests\Fixtures\TestMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer as KafkaProducer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 *
 * @requires extension rdkafka
 */
class KafkaTransportTest extends TestCase
{
    /** @var MockObject|LoggerInterface */
    private $logger;

    /** @var MockObject|SerializerInterface */
    private $serializer;

    /** @var MockObject|KafkaConsumer */
    private $rdKafkaConsumer;

    /** @var MockObject|KafkaProducer */
    private $rdKafkaProducer;

    /** @var MockObject|RdKafkaFactory */
    private $rdKafkaFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->serializer = $this->createMock(SerializerInterface::class);

        // RdKafka
        $this->rdKafkaFactory = $this->createMock(RdKafkaFactory::class);

        $this->rdKafkaConsumer = $this->createMock(KafkaConsumer::class);
        $this->rdKafkaFactory
            ->method('createConsumer')
            ->willReturn($this->rdKafkaConsumer);

        $this->rdKafkaProducer = $this->createMock(KafkaProducer::class);
        $this->rdKafkaFactory
            ->method('createProducer')
            ->willReturn($this->rdKafkaProducer);
    }

    public function testConstruct(): void
    {
        $transport = new KafkaTransport(
            $this->logger,
            $this->serializer,
            new RdKafkaFactory(),
            []
        );

        static::assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testGet(): void
    {
        $this->rdKafkaConsumer
            ->method('subscribe');

        $testMessage = new Message();
        $testMessage->err = \RD_KAFKA_RESP_ERR_NO_ERROR;
        $testMessage->topic_name = 'test';
        $testMessage->partition = 0;
        $testMessage->headers = [
            'type' => TestMessage::class,
            'Content-Type' => 'application/json',
        ];
        $testMessage->payload = '{"data":null}';
        $testMessage->offset = 0;
        $testMessage->timestamp = 1586861356;

        $this->rdKafkaConsumer
            ->method('consume')
            ->willReturn($testMessage);

        $this->serializer->expects(static::once())
            ->method('decode')
            ->with([
                'body' => '{"data":null}',
                'headers' => [
                    'type' => TestMessage::class,
                    'Content-Type' => 'application/json',
                ],
                'key' => null,
                'partition' => 0,
                'offset' => 0,
                'timestamp' => 1586861356,
                'topic_name' => 'test',
            ])
            ->willReturn(new Envelope(new TestMessage()));

        $transport = new KafkaTransport(
            $this->logger,
            $this->serializer,
            $this->rdKafkaFactory,
            [
                'conf' => [],
                'consumer' => [
                    'topics' => [
                        'test',
                    ],
                    'receive_timeout' => 10000,
                    'conf' => [],
                ],
            ]
        );

        $receivedMessages = $transport->get();
        static::assertArrayHasKey(0, $receivedMessages);

        $receivedMessage = $receivedMessages[0];
        static::assertInstanceOf(Envelope::class, $receivedMessage);
        static::assertInstanceOf(TestMessage::class, $receivedMessage->getMessage());

        $stamps = $receivedMessage->all();
        static::assertCount(1, $stamps);
        static::assertArrayHasKey(KafkaMessageStamp::class, $stamps);

        $kafkaMessageStamps = $stamps[KafkaMessageStamp::class];
        static::assertCount(1, $kafkaMessageStamps);

        /** @var KafkaMessageStamp $kafkaMessageStamp */
        $kafkaMessageStamp = $kafkaMessageStamps[0];
        static::assertSame($testMessage, $kafkaMessageStamp->getMessage());
    }
}
