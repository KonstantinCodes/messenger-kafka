<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class RestProxyTransport implements TransportInterface, MessageCountAwareInterface
{
    /** @var UriInterface */
    private $baseUri;

    /** @var string */
    private $topicName;

    /** @var SerializerInterface */
    private $serializer;

    /** @var ClientInterface */
    private $client;

    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var UriFactoryInterface */
    private $uriFactory;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    /** @var LoggerInterface|null */
    private $logger;

    private $receiver;

    /** @var RestProxySender */
    private $sender;

    public function __construct(
        UriInterface $baseUri,
        string $topicName,
        SerializerInterface $serializer,
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->baseUri = $baseUri;
        $this->topicName = $topicName;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->streamFactory = $streamFactory;
    }

    public function get(): iterable
    {
        throw new \Exception('Not implemented!');
    }

    public function ack(Envelope $envelope): void
    {
        throw new \Exception('Not implemented!');
    }

    public function reject(Envelope $envelope): void
    {
        throw new \Exception('Not implemented!');
    }

    public function send(Envelope $envelope): Envelope
    {
        return ($this->sender ?? $this->getSender())->send($envelope);
    }

    public function getMessageCount(): int
    {
        throw new \Exception('Not implemented!');
    }

    private function getSender(): RestProxySender
    {
        return $this->sender = new RestProxySender(
            $this->baseUri,
            $this->topicName,
            $this->serializer,
            $this->client,
            $this->requestFactory,
            $this->uriFactory,
            $this->streamFactory
        );
    }
}
