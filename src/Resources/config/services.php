<?php

declare(strict_types=1);
use Koco\Kafka\Transport\KafkaTransportFactory;
use Koco\Kafka\Transport\RdKafkaFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerBuilder $container): void {
    $container->register(RdKafkaFactory::class);
    $container->register(KafkaTransportFactory::class)
        ->addTag('messenger.transport_factory')
        ->setArguments([
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(RdKafkaFactory::class),
        ]);
};
