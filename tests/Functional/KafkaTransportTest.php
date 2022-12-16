<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Functional;

use Closure;
use Koco\Kafka\Messenger\KafkaTransport;
use Koco\Kafka\Messenger\KafkaTransportFactory;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

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
    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->factory = new KafkaTransportFactory(new RdKafkaFactory(), $this->logger);

        $this->serializerMock = $this->createMock(SerializerInterface::class);

        ++$this->testIteration;

        $this->testStartTime = $this->testStartTime ?? new \DateTimeImmutable();
    }

    public function serializerProvider()
    {
        $serializer = new Serializer();
        $phpSerializer = new PhpSerializer();

        return [
            [
                $serializer,
                $this->createSerializerDecodeClosure($serializer),
            ],
            [
                $phpSerializer,
                $this->createPHPSerializerDecodeClosure($phpSerializer),
            ],
        ];
    }

    /**
     * @dataProvider serializerProvider
     */
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
        $this->assertStatsCb($receiver, '0');

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

    public function testReceiverWithStatsCb()
    {
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
                    'statistics.interval.ms' => '10000',
                ],
            ],
            $this->serializerMock
        );
        $this->assertStatsCb($receiver, '10000', true);
    }

    public function createSerializerDecodeClosure(SerializerInterface $serializer): Closure
    {
        return function (array $encodedEnvelope) use ($serializer) {
            $this->assertIsArray($encodedEnvelope);

            $this->assertSame('{"data":"my_test_data"}', $encodedEnvelope['body']);

            $this->assertArrayHasKey('headers', $encodedEnvelope);
            $headers = $encodedEnvelope['headers'];

            $this->assertSame(TestMessage::class, $headers['type']);
            $this->assertSame('application/json', $headers['Content-Type']);

            return $serializer->decode($encodedEnvelope);
        };
    }

    public function createPHPSerializerDecodeClosure(SerializerInterface $serializer): Closure
    {
        return function (array $encodedEnvelope) use ($serializer) {
            $this->assertIsArray($encodedEnvelope);

            $this->assertSame(
                'O:36:\"Symfony\\\\Component\\\\Messenger\\\\Envelope\":2:{s:44:\"\0Symfony\\\\Component\\\\Messenger\\\\Envelope\0stamps\";a:0:{}s:45:\"\0Symfony\\\\Component\\\\Messenger\\\\Envelope\0message\";O:39:\"Koco\\\\Kafka\\\\Tests\\\\Functional\\\\TestMessage\":1:{s:4:\"data\";s:12:\"my_test_data\";}}',
                $encodedEnvelope['body']
            );

            $this->assertArrayHasKey('headers', $encodedEnvelope);

            return $serializer->decode($encodedEnvelope);
        };
    }

    private function getTopicName()
    {
        return self::TOPIC_NAME . '_' . $this->testStartTime->getTimestamp() . '_' . $this->testIteration;
    }

    private function assertStatsCb(TransportInterface $receiver, string $interval, bool $statsCb = false): void
    {
        $self = $this;
        $closure = function (KafkaTransport $receiver) use ($interval, $statsCb, $self){
            $conf = $receiver->kafkaReceiverProperties->getKafkaConf();
            $self::assertEquals($interval, $conf->dump()['statistics.interval.ms']);
            if ($statsCb) {
                $self::assertArrayHasKey('stats_cb', $conf->dump());
            } else {
                $self::assertArrayNotHasKey('stats_cb', $conf->dump());
            }
        };

        $doClosure = $closure->bindTo($receiver, KafkaTransport::class);
        $doClosure($receiver);
    }
}
