<?php


namespace Koco\Kafka\Tests\Functional;


use Koco\Kafka\Messenger\KafkaRestProxySender;
use Koco\Kafka\Messenger\KafkaRestProxyTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaRestProxySenderTest extends TestCase
{
    public function testSend()
    {
        $sender = new KafkaRestProxySender();

        $envelope = Envelope::wrap(new TestMessage('my_test_data'), []);
    }
}