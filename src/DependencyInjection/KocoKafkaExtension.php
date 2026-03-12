<?php

declare(strict_types=1);

namespace Koco\Kafka\DependencyInjection;

use Koco\Kafka\Messenger\KafkaTransportFactory;
use Koco\Kafka\Messenger\RestProxyTransportFactory;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class KocoKafkaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register(RdKafkaFactory::class, RdKafkaFactory::class)
            ->setPublic(false);

        $container->register(KafkaTransportFactory::class, KafkaTransportFactory::class)
            ->setPublic(false)
            ->addTag('messenger.transport_factory')
            ->setArguments([
                new Reference(RdKafkaFactory::class),
                new Reference(LoggerInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            ]);

        $container->register(RestProxyTransportFactory::class, RestProxyTransportFactory::class)
            ->setPublic(false)
            ->addTag('messenger.transport_factory')
            ->setArguments([
                new Reference(LoggerInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                new Reference(ClientInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                new Reference(RequestFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                new Reference(UriFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                new Reference(StreamFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            ]);
    }
}
