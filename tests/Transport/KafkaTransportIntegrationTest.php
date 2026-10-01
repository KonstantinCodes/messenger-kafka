<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Transport;

use Koco\Kafka\Tests\Fixtures\TestMessage;
use Koco\Kafka\Transport\KafkaTransportFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 *
 * @requires extension rdkafka
 *
 * @group integration
 */
class KafkaTransportIntegrationTest extends TestCase
{
    private const TOPIC_NAME = 'messenger_test';

    /**
     * @var false|string|null
     */
    private $dsn;

    /** @var KafkaTransportFactory */
    private $factory;

    /** @var SerializerInterface */
    private $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        if (!getenv('MESSENGER_KAFKA_DSN')) {
            self::markTestSkipped('The "MESSENGER_KAFKA_DSN" environment variable is required.');
        }

        $this->dsn = getenv('MESSENGER_KAFKA_DSN');

        $this->factory = new KafkaTransportFactory(new NullLogger());

        $this->serializer = $this->createMock(SerializerInterface::class);
    }

    public function testSendAndReceive(): void
    {
        $serializer = new Serializer();
        $topicName = $this->getTopicName('test_send_and_receive');

        $options = [
            'conf' => [],
            'consumer' => [
                'topics' => [$topicName],
                'commit_async' => false,
                'receive_timeout' => 1000,
                'conf' => [
                    'group.id' => 'messenger_test' . $topicName,
                    'enable.auto.offset.store' => 'false',
                    'enable.auto.commit' => 'false',
                    'session.timeout.ms' => '10000',
                    'auto.offset.reset' => 'earliest',
                ],
            ],
            'producer' => [
                'topic_name' => $topicName,
                'flush_timeout' => 10000,
                'flush_retries' => 10,
                'conf' => [],
            ],
        ];

        $envelope = Envelope::wrap(new TestMessage('my_test_data'), []);
        $receiver = $this->factory->createTransport($this->dsn, $options, $this->serializer);

        $this->serializer
            ->method('decode')
            ->willReturnCallback(
                function (array $encodedEnvelope) use ($serializer) {
                    $this->assertIsArray($encodedEnvelope);

                    $this->assertSame('{"data":"my_test_data"}', $encodedEnvelope['body']);

                    $this->assertArrayHasKey('headers', $encodedEnvelope);
                    $headers = $encodedEnvelope['headers'];

                    $this->assertSame(TestMessage::class, $headers['type']);
                    $this->assertSame('application/json', $headers['Content-Type']);

                    return $serializer->decode($encodedEnvelope);
                },
            );

        $sender = $this->factory->createTransport($this->dsn, $options, $serializer);
        $sender->send($envelope);

        /** @var []Envelope $envelopes */
        $envelopes = $this->receive($receiver);
        self::assertInstanceOf(Envelope::class, $envelopes[0]);

        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(TestMessage::class, $message);

        $receiver->ack($envelopes[0]);
    }

    public function testReceiveFromTwoTopics(): void
    {
        $serializer = new Serializer();
        $topicName = $this->getTopicName('test_receive_from_two_topics');
        $topicNameA = $topicName . '_A';
        $topicNameB = $topicName . '_B';

        $senderA = $this->factory->createTransport(
            $this->dsn,
            [
                'conf' => [],
                'consumer' => [],
                'producer' => [
                    'topic_name' => $topicNameA,
                    'flush_timeout' => 10000,
                    'flush_retries' => 10,
                    'conf' => [],
                ],
            ],
            $serializer,
        );

        $senderB = $this->factory->createTransport(
            $this->dsn,
            [
                'conf' => [],
                'consumer' => [],
                'producer' => [
                    'topic_name' => $topicNameB,
                    'flush_timeout' => 10000,
                    'flush_retries' => 10,
                    'conf' => [],
                ],
            ],
            $serializer,
        );

        $senderA->send(Envelope::wrap(new TestMessage('my_test_data_1'), []));
        $senderB->send(Envelope::wrap(new TestMessage('my_test_data_2'), []));

        $receiver = $this->factory->createTransport(
            $this->dsn,
            [
                'conf' => [],
                'consumer' => [
                    'topics' => [$topicNameA, $topicNameB],
                    'commit_async' => false,
                    'receive_timeout' => 1000,
                    'conf' => [
                        'group.id' => 'messenger_test_' . $topicName,
                        'enable.auto.offset.store' => 'false',
                        'enable.auto.commit' => 'false',
                        'session.timeout.ms' => '10000',
                        'auto.offset.reset' => 'earliest',
                    ],
                ],
                'producer' => [],
            ],
            $serializer,
        );

        /** @var []Envelope $envelopes */
        $envelopes1 = $this->receive($receiver);
        self::assertInstanceOf(TestMessage::class, $envelopes1[0]->getMessage());
        $receiver->ack($envelopes1[0]);

        /** @var []Envelope $envelopes */
        $envelopes2 = $this->receive($receiver);
        self::assertInstanceOf(TestMessage::class, $envelopes2[0]->getMessage());
        $receiver->ack($envelopes2[0]);
        self::assertEqualsCanonicalizing(['my_test_data_1', 'my_test_data_2'], [$envelopes1[0]->getMessage()->data, $envelopes2[0]->getMessage()->data]);
        $stamp = \Koco\Kafka\Transport\KafkaMessageStamp::class;
        self::assertEqualsCanonicalizing([$topicNameA, $topicNameB], [$envelopes1[0]->last($stamp)->getMessage()->topic_name, $envelopes2[0]->last($stamp)->getMessage()->topic_name]);
    }

    private function receive(\Symfony\Component\Messenger\Transport\TransportInterface $receiver): array
    {
        $deadline = microtime(true) + 30;
        do {
            $envelopes = [];
            foreach ($receiver->get() as $envelope) {
                $envelopes[] = $envelope;
            }
            if ($envelopes) {
                return $envelopes;
            }
        } while (microtime(true) < $deadline);
        self::fail('Timed out waiting for a Kafka message.');
    }

    private function getTopicName(string $name): string
    {
        return self::TOPIC_NAME . '_' . bin2hex(random_bytes(6)) . '_' . $name;
    }
}
