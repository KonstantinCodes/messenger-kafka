<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Functional;

use Closure;
use Koco\Kafka\Messenger\KafkaTransportFactory;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private KafkaTransportFactory $factory;
    private SerializerInterface $serializerMock;
    private int $testIteration = 0;
    private \DateTimeInterface $testStartTime;

    protected function setUp(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $this->factory = new KafkaTransportFactory(new RdKafkaFactory(), $logger);

        $this->serializerMock = $this->createMock(SerializerInterface::class);

        ++$this->testIteration;

        $this->testStartTime = $this->testStartTime ?? new \DateTimeImmutable();
    }

    public static function serializerProvider(): array
    {
        $serializer = new Serializer();
        $phpSerializer = new PhpSerializer();

        return [
            [
                $serializer,
                static fn (array $encodedEnvelope) => self::assertAndDecodeSerializer($serializer, $encodedEnvelope),
            ],
            [
                $phpSerializer,
                static fn (array $encodedEnvelope) => self::assertAndDecodePHPSerializer($phpSerializer, $encodedEnvelope),
            ],
        ];
    }

    #[DataProvider('serializerProvider')]
    public function testSendAndReceive(SerializerInterface $serializer, Closure $decodeClosure)
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

        $this->serializerMock->expects(static::once())
            ->method('decode')
            ->willReturnCallback($decodeClosure);

        /** @var []Envelope $envelopes */
        $envelopes = $receiver->get();
        static::assertInstanceOf(Envelope::class, $envelopes[0]);

        $message = $envelopes[0]->getMessage();
        static::assertInstanceOf(TestMessage::class, $message);

        $receiver->ack($envelopes[0]);
    }

    private static function assertAndDecodeSerializer(SerializerInterface $serializer, array $encodedEnvelope): Envelope
    {
        self::assertIsArray($encodedEnvelope);
        self::assertSame('{"data":"my_test_data"}', $encodedEnvelope['body']);
        self::assertArrayHasKey('headers', $encodedEnvelope);

        $headers = $encodedEnvelope['headers'];
        self::assertSame(TestMessage::class, $headers['type']);
        self::assertSame('application/json', $headers['Content-Type']);

        return $serializer->decode($encodedEnvelope);
    }

    private static function assertAndDecodePHPSerializer(SerializerInterface $serializer, array $encodedEnvelope): Envelope
    {
        self::assertIsArray($encodedEnvelope);
        self::assertSame(
            'O:36:\"Symfony\\\\Component\\\\Messenger\\\\Envelope\":2:{s:44:\"\0Symfony\\\\Component\\\\Messenger\\\\Envelope\0stamps\";a:0:{}s:45:\"\0Symfony\\\\Component\\\\Messenger\\\\Envelope\0message\";O:39:\"Koco\\\\Kafka\\\\Tests\\\\Functional\\\\TestMessage\":1:{s:4:\"data\";s:12:\"my_test_data\";}}',
            $encodedEnvelope['body']
        );
        self::assertArrayHasKey('headers', $encodedEnvelope);

        return $serializer->decode($encodedEnvelope);
    }

    private function getTopicName(): string
    {
        return self::TOPIC_NAME . '_' . $this->testStartTime->getTimestamp() . '_' . $this->testIteration;
    }
}
