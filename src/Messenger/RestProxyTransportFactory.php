<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use function strpos;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class RestProxyTransportFactory implements TransportFactoryInterface
{
    private const DSN_PROTOCOL_KAFKA_REST = 'kafka+rest';
    private const DSN_PROTOCOL_KAFKA_REST_SSL = 'kafka+rest+ssl';

    /** @var LoggerInterface|null */
    private $logger;

    /** @var ClientInterface|null */
    private $client;

    /** @var RequestFactoryInterface|null */
    private $requestFactory;

    /** @var UriFactoryInterface|null */
    private $uriFactory;

    /** @var StreamFactoryInterface|null */
    private $streamFactory;

    public function __construct(
        ?LoggerInterface $logger,
        ?ClientInterface $client,
        ?RequestFactoryInterface $requestFactory,
        ?UriFactoryInterface $uriFactory,
        ?StreamFactoryInterface $streamFactory
    ) {
        $this->logger = $logger;
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->streamFactory = $streamFactory;
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, static::DSN_PROTOCOL_KAFKA_REST);
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $this->checkDependencies();

        $dsn = $this->uriFactory->createUri($dsn);
        $scheme = $dsn->getScheme();

        $dsnOptions = $this->queryStringToOptionsArray($dsn->getQuery());
        $options = array_merge($dsnOptions, $options); // Override DSN options with options array

        $baseUri = $dsn->withQuery('');

        if ($scheme === static::DSN_PROTOCOL_KAFKA_REST) {
            $baseUri = $baseUri->withScheme('http');
        } elseif ($scheme === static::DSN_PROTOCOL_KAFKA_REST_SSL) {
            $baseUri = $baseUri->withScheme('https');
        } else {
            throw new \InvalidArgumentException('The DSN is not formatted as expected.');
        }

        return new RestProxyTransport(
            $baseUri,
            $options['topic'],
            $serializer,
            $this->client,
            $this->requestFactory,
            $this->uriFactory,
            $this->streamFactory,
            $this->logger
        );
    }

    private function queryStringToOptionsArray(string $queryString): array
    {
        $queryParts = explode('&', $queryString) ?? [];

        $dsnOptions = [];
        foreach ($queryParts as $queryPart) {
            list($key, $value) = explode('=', $queryPart);
            $dsnOptions[$key] = urldecode($value);
        }

        return $dsnOptions;
    }

    private function checkDependencies(): void
    {
        if ($this->client === null) {
            throw $this->createMissingServiceException(ClientInterface::class, 'PSR-7 HTTP Client not found.');
        }

        if ($this->requestFactory === null) {
            throw $this->createMissingServiceException(RequestFactoryInterface::class, 'PSR HTTP RequestFactory not found.');
        }

        if ($this->uriFactory === null) {
            throw $this->createMissingServiceException(UriFactoryInterface::class, 'PSR HTTP UriFactory not found.');
        }

        if ($this->streamFactory === null) {
            throw $this->createMissingServiceException(StreamFactoryInterface::class, 'PSR HTTP StreamFactory not found.');
        }
    }

    private function createMissingServiceException(string $className, ?string $message = null)
    {
        return new \InvalidArgumentException(sprintf(
            '%sPlease install a library that provides "%s" and ensure the service is registered.',
            $message ? $message . ' ' : '',
            $className
        ));
    }
}
