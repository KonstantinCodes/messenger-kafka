<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Transport;

use Koco\Kafka\Transport\KafkaOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

class KafkaOptionsTest extends TestCase
{
    public function testDefaultsAndQueryTypes(): void
    {
        $options = KafkaOptions::resolve(['producer' => ['flush_retries' => '2'], 'consumer' => ['commit_async' => 'false'], 'conf' => ['enable.auto.commit' => false]]);
        self::assertSame(10000, $options['producer']['flush_timeout']);
        self::assertSame(2, $options['producer']['flush_retries']);
        self::assertSame(10000, $options['consumer']['receive_timeout']);
        self::assertFalse($options['consumer']['commit_async']);
        self::assertSame('false', $options['conf']['enable.auto.commit']);
    }

    /** @dataProvider provideRejectsInvalidConfigurationCases */
    public function testRejectsInvalidConfiguration(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        KafkaOptions::resolve($options);
    }

    public static function provideRejectsInvalidConfigurationCases(): iterable
    {
        yield [['flushTimeout' => 100]];
        yield [['producer' => ['flush_timeout' => -1]]];
        yield [['producer' => ['flush_retries' => false]]];
        yield [['producer' => ['flush_retries' => 'many']]];
        yield [['producer' => ['topic_name' => '']]];
        yield [['consumer' => ['topics' => 'events']]];
        yield [['consumer' => ['topics' => ['']]]];
        yield [['consumer' => ['commit_async' => 'sometimes']]];
        yield [['consumer' => ['receive_timeout' => 1.5]]];
        yield [['consumer' => ['conf' => ['group.id' => []]]]];
        yield [['producer' => 'events']];
        yield [['conf' => [0 => 'value']]];
        yield [['consumer' => ['unknown' => true]]];
    }
}
