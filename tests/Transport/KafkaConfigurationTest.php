<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Transport;

use Koco\Kafka\Transport\KafkaTransportFactory;
use Koco\Kafka\Transport\RdKafkaFactory;
use PHPUnit\Framework\TestCase;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class KafkaConfigurationTest extends TestCase
{
    /** @dataProvider provideBrokersAndConfigurationPrecedenceCases */
    public function testBrokersAndConfigurationPrecedence(string $dsn, string $brokers): void
    {
        $factory = $this->createMock(RdKafkaFactory::class);
        $message = new Message();
        $message->err = RD_KAFKA_RESP_ERR__TIMED_OUT;
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->expects(self::once())->method('subscribe')->with(['explicit']);
        $consumer->method('consume')->willReturn($message);
        $factory->expects(self::once())->method('createConsumer')->with(self::callback(static function (Conf $conf) use ($brokers): bool {
            $values = $conf->dump();
            self::assertSame($brokers, $values['metadata.broker.list']);
            self::assertSame('consumer-group', $values['group.id']);

            return true;
        }))->willReturn($consumer);
        $transportFactory = new KafkaTransportFactory(null, $factory);
        self::assertTrue($transportFactory->supports($dsn, []));
        $transport = $transportFactory->createTransport($dsn . '?consumer[topics][]=query-a&consumer[topics][]=query-b&consumer[receive_timeout]=250', [
            'transport_name' => 'framework-internal-name',
            'conf' => ['group.id' => 'shared-group'],
            'consumer' => ['topics' => ['explicit'], 'conf' => ['group.id' => 'consumer-group']],
        ], new PhpSerializer());
        self::assertSame([], $transport->get());
    }

    public static function provideBrokersAndConfigurationPrecedenceCases(): iterable
    {
        yield ['kafka://broker1:9092,broker2:9093', 'broker1:9092,broker2:9093'];
        yield ['kafka+ssl://broker1:9093,kafka+ssl://broker2:9093', 'broker1:9093,broker2:9093'];
        yield ['kafka://broker1:9092,kafka://broker2:9093', 'broker1:9092,broker2:9093'];
        yield ['kafka://localhost', 'localhost:9092'];
        yield ['kafka://[::1]:9092', '[::1]:9092'];
    }

    /** @dataProvider provideInvalidDsnCases */
    public function testInvalidDsn(string $dsn): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new KafkaTransportFactory())->createTransport($dsn, [], new PhpSerializer());
    }

    public static function provideInvalidDsnCases(): iterable
    {
        yield ['http://localhost'];
        yield ['kafka://'];
        yield ['kafka://localhost:99999'];
        yield ['kafka://localhost:0'];
        yield ['kafka://localhost/path'];
        yield ['kafka://user:password@localhost'];
        yield ['kafka://localhost,'];
    }
}
