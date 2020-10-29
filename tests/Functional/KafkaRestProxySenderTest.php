<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Functional;

use Koco\Kafka\Messenger\KafkaRestProxySender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

class KafkaRestProxySenderTest extends TestCase
{
    public function testSend()
    {
        $sender = new KafkaRestProxySender();

        $envelope = Envelope::wrap(new TestMessage('my_test_data'), []);
    }
}
