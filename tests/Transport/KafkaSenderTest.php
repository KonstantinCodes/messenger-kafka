<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Transport;

use Koco\Kafka\Tests\Fixtures\TestMessage;
use Koco\Kafka\Transport\KafkaSender;
use Koco\Kafka\Transport\RdKafkaFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RdKafka\Conf;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaSenderTest extends TestCase
{
    /**
     * @dataProvider provideSendCases
     */
    public function testSend(array $flushResults, int $retries): void
    {
        $envelope = new Envelope(new TestMessage());
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('encode')->with($envelope)->willReturn([
            'body' => '{"data":"test"}',
            'key' => 'event-123',
            'headers' => ['type' => TestMessage::class],
            'timestamp_ms' => 1586861356000,
        ]);

        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects(self::once())->method('producev')->with(
            RD_KAFKA_PARTITION_UA,
            0,
            '{"data":"test"}',
            'event-123',
            ['type' => TestMessage::class],
            1586861356000,
        );
        $producer = $this->createMock(Producer::class);
        $producer->expects(self::once())->method('newTopic')->with('events')->willReturn($topic);
        $producer->expects(self::once())->method('poll')->with(0);
        $producer->expects(self::exactly(\count($flushResults)))->method('flush')
            ->with(250)->willReturnOnConsecutiveCalls(...$flushResults);
        $factory = $this->createMock(RdKafkaFactory::class);
        $factory->expects(self::once())->method('createProducer')->willReturn($producer);
        $sender = new KafkaSender(new NullLogger(), $serializer, $factory, new Conf(), ['topic_name' => 'events', 'flush_timeout' => 250, 'flush_retries' => $retries]);

        $lastResult = $flushResults[\count($flushResults) - 1];
        if ($lastResult !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            $this->expectException(TransportException::class);
            $this->expectExceptionCode($lastResult);
        }

        self::assertSame($envelope, $sender->send($envelope));
    }

    public function testTopicIsReusedAndBatchHandlerStampDoesNotSkipFlush(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => 'payload']);
        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects(self::exactly(2))->method('producev');
        $producer = $this->createMock(Producer::class);
        $producer->expects(self::once())->method('newTopic')->with('events')->willReturn($topic);
        $producer->expects(self::exactly(2))->method('poll')->with(0);
        $producer->expects(self::exactly(2))->method('flush')->with(100)->willReturn(RD_KAFKA_RESP_ERR_NO_ERROR);
        $factory = $this->createMock(RdKafkaFactory::class);
        $factory->expects(self::once())->method('createProducer')->willReturn($producer);
        $sender = new KafkaSender(new NullLogger(), $serializer, $factory, new Conf(), ['topic_name' => 'events', 'flush_timeout' => 100, 'flush_retries' => 0]);
        $envelope = new Envelope(new TestMessage(), [new \Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp(false)]);
        self::assertSame($envelope, $sender->send($envelope));
        self::assertSame($envelope, $sender->send($envelope));
    }

    public static function provideSendCases(): iterable
    {
        yield 'success stops retries immediately' => [[RD_KAFKA_RESP_ERR_NO_ERROR], 3];
        yield 'retry succeeds without producing a duplicate' => [[RD_KAFKA_RESP_ERR__TIMED_OUT, RD_KAFKA_RESP_ERR_NO_ERROR], 3];
        yield 'retry budget exhausted' => [[RD_KAFKA_RESP_ERR__TIMED_OUT, RD_KAFKA_RESP_ERR__TIMED_OUT, RD_KAFKA_RESP_ERR__TIMED_OUT], 2];
        yield 'zero retries still attempts one flush' => [[RD_KAFKA_RESP_ERR__TIMED_OUT], 0];
    }
}
