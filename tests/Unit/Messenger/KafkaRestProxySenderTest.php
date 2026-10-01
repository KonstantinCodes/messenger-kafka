<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Unit\Messenger;

use Koco\Kafka\Messenger\RestProxySender;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaRestProxySenderTest extends TestCase
{
    /**
     * @dataProvider provideSendCases
     */
    public function testSend(int $statusCode): void
    {
        $envelope = new Envelope(new TestMessage());
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('encode')->with($envelope)->willReturn([
            'key' => base64_encode('event-123'),
            'body' => base64_encode('hello'),
            'headers' => ['Content-Type' => 'application/vnd.kafka.binary.v2+json'],
        ]);
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->with(self::callback(static function (RequestInterface $request): bool {
            self::assertSame('POST', $request->getMethod());
            self::assertSame('https://example.com:8082/topics/events', (string) $request->getUri());
            self::assertSame('application/vnd.kafka.v2+json', $request->getHeaderLine('Accept'));
            self::assertSame('application/vnd.kafka.binary.v2+json', $request->getHeaderLine('Content-Type'));
            self::assertSame([
                'records' => [['key' => base64_encode('event-123'), 'value' => base64_encode('hello')]],
            ], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));

            return true;
        }))->willReturn(new Response($statusCode));
        $psr17Factory = new Psr17Factory();
        $sender = new RestProxySender(new Uri('https://example.com:8082'), 'events', $serializer, $client, $psr17Factory, $psr17Factory, $psr17Factory);

        self::assertSame($envelope, $sender->send($envelope));
    }

    public static function provideSendCases(): iterable
    {
        yield 'OK' => [200];
        yield 'no content' => [204];
    }
}
