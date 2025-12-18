<?php

declare(strict_types=1);

use Koco\Kafka\Messenger\KafkaTransportFactory;
use Koco\Kafka\Messenger\RestProxyTransportFactory;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(RdKafkaFactory::class)
            ->class(RdKafkaFactory::class)
        ->set(KafkaTransportFactory::class)
            ->tag('messenger.transport_factory')
            ->class(KafkaTransportFactory::class)
                ->args([
                    service(RdKafkaFactory::class),
                    service('logger')->nullOnInvalid(),
                ])
        ->set(RestProxyTransportFactory::class)
            ->tag('messenger.transport_factory')
            ->class(RestProxyTransportFactory::class)
                ->args([
                    service('logger')->nullOnInvalid(),
                    service(ClientInterface::class)->nullOnInvalid(),
                    service(RequestFactoryInterface::class)->nullOnInvalid(),
                    service(UriFactoryInterface::class)->nullOnInvalid(),
                    service(StreamFactoryInterface::class)->nullOnInvalid(),
                ]);
};
