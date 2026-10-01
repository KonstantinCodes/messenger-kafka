<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Transport;

use Koco\Kafka\Tests\Fixtures\TestMessage;
use Koco\Kafka\Transport\KafkaMessageStamp;
use Koco\Kafka\Transport\KafkaReceiver;
use Koco\Kafka\Transport\RdKafkaFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaReceiverTest extends TestCase
{
    /**
     * @dataProvider provideEmptyPollCases
     */
    public function testEmptyPoll(int $error): void
    {
        $message = new Message();
        $message->err = $error;
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->expects(self::once())->method('subscribe')->with(['events']);
        $consumer->expects(self::exactly(2))->method('consume')->with(250)->willReturn($message);
        $consumer->expects(self::never())->method('commit');
        $consumer->expects(self::never())->method('commitAsync');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('decode');
        $receiver = $this->createReceiver($consumer, $serializer);

        self::assertSame([], $receiver->get());
        self::assertSame([], $receiver->get());
    }

    public static function provideEmptyPollCases(): iterable
    {
        yield 'timeout' => [RD_KAFKA_RESP_ERR__TIMED_OUT];
        yield 'partition EOF' => [RD_KAFKA_RESP_ERR__PARTITION_EOF];
        yield 'broker transport failure' => [RD_KAFKA_RESP_ERR__TRANSPORT];
    }

    /**
     * @dataProvider provideAckCases
     */
    public function testAck(bool $async): void
    {
        $message = new Message();
        $message->topic_name = 'events';
        $message->partition = 2;
        $message->offset = 42;
        $envelope = new Envelope(new TestMessage(), [new KafkaMessageStamp($message)]);
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->expects(self::once())->method($async ? 'commitAsync' : 'commit')->with(self::identicalTo($message));
        $consumer->expects(self::never())->method($async ? 'commit' : 'commitAsync');
        $consumer->expects(self::never())->method('subscribe');
        $consumer->expects(self::never())->method('consume');

        $this->createReceiver($consumer, $this->createMock(SerializerInterface::class), $async)->ack($envelope);
    }

    public static function provideAckCases(): iterable
    {
        yield 'synchronous' => [false];
        yield 'asynchronous' => [true];
    }

    public function testRejectDoesNotCreateAConsumerOrCommit(): void
    {
        $factory = $this->createMock(RdKafkaFactory::class);
        $factory->expects(self::never())->method('createConsumer');
        $receiver = new KafkaReceiver(new NullLogger(), $this->createMock(SerializerInterface::class), $factory, new Conf(), ['topics' => ['events'], 'receive_timeout' => 250, 'commit_async' => false]);

        $receiver->reject(new Envelope(new TestMessage(), [new KafkaMessageStamp(new Message())]));
    }

    public function testUnexpectedKafkaErrorThrows(): void
    {
        $message = new Message();
        $message->err = RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART;
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->method('consume')->willReturn($message);
        $consumer->expects(self::never())->method('commit');
        $consumer->expects(self::never())->method('commitAsync');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('decode');

        $this->expectException(TransportException::class);
        $this->expectExceptionCode(RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART);
        $this->createReceiver($consumer, $serializer)->get();
    }

    public function testDecodingFailureDoesNotCommit(): void
    {
        $message = new Message();
        $message->err = RD_KAFKA_RESP_ERR_NO_ERROR;
        $message->topic_name = 'events';
        $message->payload = 'invalid message';
        $message->partition = 0;
        $message->offset = 42;
        $message->timestamp = 1586861356000;
        $message->key = null;
        $message->headers = [];
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->method('consume')->willReturn($message);
        $consumer->expects(self::never())->method('commit');
        $consumer->expects(self::never())->method('commitAsync');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('decode')->willThrowException(new MessageDecodingFailedException('Cannot decode message'));

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Cannot decode message');
        $this->createReceiver($consumer, $serializer)->get();
    }

    public function testMissingStampDoesNotCreateConsumer(): void
    {
        $factory = $this->createMock(RdKafkaFactory::class);
        $factory->expects(self::never())->method('createConsumer');
        $receiver = new KafkaReceiver(new NullLogger(), $this->createMock(SerializerInterface::class), $factory, new Conf(), []);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('KafkaMessageStamp is missing');
        $receiver->ack(new Envelope(new TestMessage()));
    }

    public function testMissingHeadersAndSourceMetadataReachSerializer(): void
    {
        $message = new Message();
        $message->err = RD_KAFKA_RESP_ERR_NO_ERROR;
        $message->payload = 'payload';
        $message->offset = 42;
        $message->timestamp = 1586861356000;
        $message->key = null;
        $message->topic_name = 'events';
        $message->partition = 3;
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->method('consume')->willReturn($message);
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('decode')->with(self::callback(static function (array $encoded): bool {
            self::assertSame([], $encoded['headers']);
            self::assertSame('events', $encoded['topic_name']);
            self::assertSame(3, $encoded['partition']);

            return true;
        }))->willReturn(new Envelope(new TestMessage()));
        self::assertCount(1, $this->createReceiver($consumer, $serializer)->get(10));
    }

    private function createReceiver(KafkaConsumer $consumer, SerializerInterface $serializer, bool $async = false): KafkaReceiver
    {
        $factory = $this->createMock(RdKafkaFactory::class);
        $factory->expects(self::once())->method('createConsumer')->willReturn($consumer);

        return new KafkaReceiver(new NullLogger(), $serializer, $factory, new Conf(), ['topics' => ['events'], 'receive_timeout' => 250, 'commit_async' => $async]);
    }
}
