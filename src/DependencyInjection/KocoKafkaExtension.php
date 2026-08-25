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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
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

        // PSR-сервисы передаются ЗАМЫКАНИЯМИ, а не ссылками. Причина: Symfony
        // инстанцирует ВСЕ фабрики транспортов мессенджера при старте, чтобы
        // опросить их supports(). Обычная ссылка заставила бы контейнер создать
        // Psr18Client уже в этот момент — а его конструктор бросает LogicException,
        // если в проекте нет реализации PSR-17. Итог: приложение, не использующее
        // REST-proxy вовсе, не поднималось (инцидент FeedbackApi 2026-08-21:
        // kafka-воркер падал на старте с «no PSR-17 factories have been provided»).
        //
        // NULL_ON_INVALID_REFERENCE тут не помогает: он защищает от ОТСУТСТВУЮЩЕГО
        // сервиса, а psr18.http_client присутствует всегда, когда включён
        // framework.http_client, — и падает при создании.
        //
        // С замыканием сервис создаётся только внутри createTransport(), то есть
        // лишь когда DSN действительно kafka+rest.
        $container->register(RestProxyTransportFactory::class, RestProxyTransportFactory::class)
            ->setPublic(false)
            ->addTag('messenger.transport_factory')
            ->setArguments([
                new Reference(LoggerInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                new ServiceClosureArgument(new Reference(ClientInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE)),
                new ServiceClosureArgument(new Reference(RequestFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE)),
                new ServiceClosureArgument(new Reference(UriFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE)),
                new ServiceClosureArgument(new Reference(StreamFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE)),
            ]);
    }
}
