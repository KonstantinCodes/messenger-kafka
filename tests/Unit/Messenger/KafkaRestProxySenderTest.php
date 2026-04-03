<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Unit\Messenger;

use Koco\Kafka\Messenger\RestProxySender;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaRestProxySenderTest extends TestCase
{
    public function testBla()
    {
        $client = new DummyClient();
        $serializer = new DummySerializer();

        $psr17Factory = new Psr17Factory();

        $sender = new RestProxySender(
            new Uri('http://example.com'),
            'test',
            $serializer,
            $client,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        //$sender->send()

        static::assertTrue(true);
    }
}

class DummyClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new \RuntimeException('Not implemented');
    }
}

class DummySerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        throw new \RuntimeException('Not implemented');
    }

    public function encode(Envelope $envelope): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
