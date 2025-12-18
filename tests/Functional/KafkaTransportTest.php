<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Functional;

use Koco\Kafka\Messenger\KafkaTransportFactory;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaTransportTest extends TestCase
{
    private const BROKER = '127.0.0.1:9092';
    private const TOPIC_NAME = 'test_topic';

    /** @var KafkaTransportFactory */
    private $factory;

    /** @var SerializerInterface */
    private $serializerMock;

    /** @var string */
    private $testIteration = 0;

    /** @var \DateTimeInterface */
    private $testStartTime;

    protected function setUp(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $this->factory = new KafkaTransportFactory(new RdKafkaFactory(), $logger);

        $this->serializerMock = $this->createMock(SerializerInterface::class);

        ++$this->testIteration;

        $this->testStartTime = $this->testStartTime ?? new \DateTimeImmutable();
    }

    public static function provideSerializer(): \Generator
    {
        $serializer = new Serializer();
        $phpSerializer = new PhpSerializer();

        yield [$serializer, self::createSerializerDecodeClosure($serializer)];
        yield [$phpSerializer, self::createPHPSerializerDecodeClosure($phpSerializer)];
    }

    /**
     * @dataProvider provideSerializer
     */
    public function testSendAndReceive(SerializerInterface $serializer, \Closure $decodeClosure)
    {
        $sender = $this->factory->createTransport(
            self::BROKER,
            [
                'flushTimeout' => 5000,
                'flushRetries' => 5,
                'topic' => [
                    'name' => $this->getTopicName(),
                ],
                'kafka_conf' => [],
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
                    'name' => $this->getTopicName(),
                ],
                'kafka_conf' => [
                    'group.id' => 'test_group',
                    'enable.auto.offset.store' => 'false',
                    'session.timeout.ms' => '10000',
                ],
                'topic_conf' => [
                    'auto.offset.reset' => 'earliest',
                ],
            ],
            $this->serializerMock
        );

        $this->serializerMock->expects(self::once())
            ->method('decode')
            ->willReturnCallback($decodeClosure);

        /** @var []Envelope $envelopes */
        $envelopes = $receiver->get();
        self::assertInstanceOf(Envelope::class, $envelopes[0]);

        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(TestMessage::class, $message);

        $receiver->ack($envelopes[0]);
    }

    public static function createSerializerDecodeClosure(SerializerInterface $serializer): \Closure
    {
        return static function (array $encodedEnvelope) use ($serializer) {
            self::assertIsArray($encodedEnvelope);

            self::assertSame('{"data":"my_test_data"}', $encodedEnvelope['body']);

            self::assertArrayHasKey('headers', $encodedEnvelope);
            $headers = $encodedEnvelope['headers'];

            self::assertSame(TestMessage::class, $headers['type']);
            self::assertSame('application/json', $headers['Content-Type']);

            return $serializer->decode($encodedEnvelope);
        };
    }

    public static function createPHPSerializerDecodeClosure(SerializerInterface $serializer): \Closure
    {
        return static function (array $encodedEnvelope) use ($serializer) {
            self::assertIsArray($encodedEnvelope);

            self::assertSame(
                'O:36:\"Symfony\\\\Component\\\\Messenger\\\\Envelope\":2:{s:44:\"\0Symfony\\\\Component\\\\Messenger\\\\Envelope\0stamps\";a:0:{}s:45:\"\0Symfony\\\\Component\\\\Messenger\\\\Envelope\0message\";O:39:\"Koco\\\\Kafka\\\\Tests\\\\Functional\\\\TestMessage\":1:{s:4:\"data\";s:12:\"my_test_data\";}}',
                $encodedEnvelope['body']
            );

            self::assertArrayHasKey('headers', $encodedEnvelope);

            return $serializer->decode($encodedEnvelope);
        };
    }

    private function getTopicName()
    {
        return self::TOPIC_NAME . '_' . $this->testStartTime->getTimestamp() . '_' . $this->testIteration;
    }
}
