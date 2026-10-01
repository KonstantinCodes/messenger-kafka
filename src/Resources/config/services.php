<?php

declare(strict_types=1);

use Koco\Kafka\Messenger\KafkaTransportFactory;
use Koco\Kafka\Messenger\RestProxyTransportFactory;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerBuilder $container): void {
    $container->register(RdKafkaFactory::class, RdKafkaFactory::class)->setPublic(false);

    $container->register(KafkaTransportFactory::class, KafkaTransportFactory::class)
        ->setPublic(false)
        ->addTag('messenger.transport_factory')
        ->setArguments([
            new Reference(RdKafkaFactory::class),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    $container->register(RestProxyTransportFactory::class, RestProxyTransportFactory::class)
        ->setPublic(false)
        ->addTag('messenger.transport_factory')
        ->setArguments([
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(ClientInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(RequestFactoryInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(UriFactoryInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(StreamFactoryInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
};
