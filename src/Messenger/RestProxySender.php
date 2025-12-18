<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class RestProxySender implements SenderInterface
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

    public function __construct(
        UriInterface $baseUri,
        string $topicName,
        SerializerInterface $serializer,
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->baseUri = $baseUri;
        $this->topicName = $topicName;
        $this->serializer = $serializer;
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->streamFactory = $streamFactory;
    }

    public function send(Envelope $envelope): Envelope
    {
        $encoded = $this->serializer->encode($envelope);

        $request = $this->requestFactory->createRequest('POST', $this->baseUri->withPath('/topics/' . $this->topicName));
        $request = $request->withHeader('Accept', 'application/vnd.kafka.v2+json');
        $request = $request->withHeader('Content-Type', $encoded['headers']['Content-Type']);
        $request = $request->withBody($this->streamFactory->createStream(
            '{ "records": [ { "key": "' . $encoded['key'] . '", "value": "' . $encoded['body'] . '" } ] }'
        ));

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() === 204) {
            return $envelope;
        }

        if ($response->getStatusCode() === 200) {
            return $envelope; // $response->getBody(); TODO: do something
        }

        return $envelope;
    }
}
