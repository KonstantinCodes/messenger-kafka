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

    private ?LoggerInterface $logger;

    /**
     * PSR-зависимости хранятся КАК ПЕРЕДАНЫ — объектом или замыканием, которое его
     * отдаст. Контейнер передаёт сюда замыкания (см. KocoKafkaExtension): иначе
     * Symfony создавал бы Psr18Client при старте приложения, опрашивая supports()
     * у всех фабрик транспортов, и падал бы в проекте без реализации PSR-17,
     * который REST-proxy вообще не использует.
     *
     * Прямая передача объектов сохранена для ручного создания и тестов.
     *
     * @var ClientInterface|\Closure|null
     */
    private $client;

    /** @var RequestFactoryInterface|\Closure|null */
    private $requestFactory;

    /** @var UriFactoryInterface|\Closure|null */
    private $uriFactory;

    /** @var StreamFactoryInterface|\Closure|null */
    private $streamFactory;

    private bool $resolved = false;

    /**
     * @param ClientInterface|\Closure|null         $client
     * @param RequestFactoryInterface|\Closure|null $requestFactory
     * @param UriFactoryInterface|\Closure|null     $uriFactory
     * @param StreamFactoryInterface|\Closure|null  $streamFactory
     */
    public function __construct(
        ?LoggerInterface $logger,
        $client,
        $requestFactory,
        $uriFactory,
        $streamFactory
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
        $this->resolveDependencies();
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
            [$key, $value] = explode('=', $queryPart);
            $dsnOptions[$key] = urldecode($value);
        }

        return $dsnOptions;
    }

    /**
     * Разворачивает замыкания в сервисы. Зовётся только из createTransport(), то
     * есть лишь для DSN kafka+rest — приложение с другими транспортами PSR-сервисы
     * не трогает вовсе.
     */
    private function resolveDependencies(): void
    {
        if ($this->resolved) {
            return;
        }

        foreach (['client', 'requestFactory', 'uriFactory', 'streamFactory'] as $property) {
            if ($this->{$property} instanceof \Closure) {
                $this->{$property} = ($this->{$property})();
            }
        }

        $this->resolved = true;
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

    private function createMissingServiceException(string $className, ?string $message = null): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf(
            '%sPlease install a library that provides "%s" and ensure the service is registered.',
            $message ? $message . ' ' : '',
            $className
        ));
    }
}
