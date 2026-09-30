<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Unit\DependencyInjection;

use Koco\Kafka\DependencyInjection\KocoKafkaExtension;
use Koco\Kafka\Messenger\KafkaTransportFactory;
use Koco\Kafka\Messenger\RestProxyTransport;
use Koco\Kafka\Messenger\RestProxyTransportFactory;
use Koco\Kafka\RdKafka\RdKafkaFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class KocoKafkaExtensionTest extends TestCase
{
    public function testFactoriesLoadWithoutOptionalServices(): void
    {
        $container = $this->createContainer();
        $container->compile();

        $factory = $container->get(KafkaTransportFactory::class);
        self::assertInstanceOf(KafkaTransportFactory::class, $factory);
        self::assertTrue($factory->supports('kafka://localhost:9092', []));

        $restFactory = $container->get(RestProxyTransportFactory::class);
        self::assertInstanceOf(RestProxyTransportFactory::class, $restFactory);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTP Client not found');
        $restFactory->createTransport('kafka+rest://localhost:8082?topic=events', [], new PhpSerializer());
    }

    public function testRestFactoryLoadsWithOptionalServices(): void
    {
        $container = $this->createContainer();
        $container->register('logger', NullLogger::class);
        $container->register(ClientInterface::class, ClientInterface::class)->setSynthetic(true)->setPublic(true);
        foreach ([RequestFactoryInterface::class, UriFactoryInterface::class, StreamFactoryInterface::class] as $interface) {
            $container->register($interface, Psr17Factory::class);
        }
        $container->compile();
        $container->set(ClientInterface::class, $this->createMock(ClientInterface::class));

        $transport = $container->get(RestProxyTransportFactory::class)->createTransport(
            'kafka+rest://localhost:8082?topic=events',
            [],
            new PhpSerializer(),
        );
        self::assertInstanceOf(RestProxyTransport::class, $transport);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new KocoKafkaExtension())->load([], $container);

        self::assertFalse($container->getDefinition(RdKafkaFactory::class)->isPublic());
        foreach ([KafkaTransportFactory::class, RestProxyTransportFactory::class] as $factory) {
            $definition = $container->getDefinition($factory);
            self::assertFalse($definition->isPublic());
            self::assertTrue($definition->hasTag('messenger.transport_factory'));
            $definition->setPublic(true);
        }

        return $container;
    }
}
