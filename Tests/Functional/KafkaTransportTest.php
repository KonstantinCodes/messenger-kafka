<?php

namespace Koco\Kafka\Tests\Functional;

use Koco\Kafka\Messenger\KafkaTransportFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaTransportTest extends TestCase
{
    private const BROKER = 'localhost:9092';
    private const TOPIC_NAME = 'test_topic';

    /** @var KafkaTransportFactory */
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

    /**
     * @group legacy
     * @expectedDeprecation Unsilenced deprecation: Function RdKafka\Conf::setDefaultTopicConf() is deprecated
     */
    public function testSendAndReceive()
    {
        $serializer = new Serializer();

        $sender = $this->factory->createTransport(
            self::BROKER,
            [
                'flushTimeout' => 1000,
                'topic' => [
                    'name' => self::TOPIC_NAME
                ],
                'kafka_conf' => []
            ],
            $serializer
        );

        $envelope = Envelope::wrap(new TestMessage('my_test_data'), []);

        $sender->send($envelope);

        $receiver = $this->factory->createTransport(
            self::BROKER,
            [
                'commitAsync' => true,
                'receiveTimeout' => 10000,
                'topic' => [
                    'name' => self::TOPIC_NAME
                ],
                'kafka_conf' => [
                    'group.id' => 'test_group',
                    'enable.auto.offset.store' => 'false'
                ],
                'topic_conf' => [
                    'auto.offset.reset' => 'smallest'
                ]
            ],
            $this->serializerMock
        );

        $this->serializerMock->expects($this->once())
            ->method('decode')
            ->willReturnCallback($this->createDecodeClosure($serializer));

        /** @var []Envelope $envelopes */
        $envelopes = $receiver->get();
        $this->assertInstanceOf(Envelope::class, $envelopes[0]);

        $message = $envelopes[0]->getMessage();
        $this->assertInstanceOf(TestMessage::class, $message);
    }

    public function createDecodeClosure(Serializer $serializer) {
        return function(array $encodedEnvelope) use ($serializer) {
            $this->assertIsArray($encodedEnvelope);

            $this->assertEquals('{"data":"my_test_data"}', $encodedEnvelope['body']);

            $this->assertArrayHasKey('headers', $encodedEnvelope);
            $headers = $encodedEnvelope['headers'];

            $this->assertEquals(TestMessage::class, $headers['type']);
            $this->assertEquals('application/json', $headers['Content-Type']);

            return $serializer->decode($encodedEnvelope);
        };
    }
}