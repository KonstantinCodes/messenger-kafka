<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Unit\Messenger;

use Koco\Kafka\Messenger\RestProxyTransportFactory;
use PHPUnit\Framework\TestCase;

/**
 * Ленивое разрешение PSR-зависимостей.
 *
 * Регрессия на инцидент 2026-08-21 (FeedbackApi): Symfony опрашивает supports() у ВСЕХ
 * фабрик транспортов при старте приложения. Пока PSR-сервисы передавались обычными
 * ссылками, контейнер создавал Psr18Client уже в этот момент — и в проекте без
 * реализации PSR-17 его конструктор бросал LogicException. Приложение, не использующее
 * REST-proxy вовсе, не поднималось: kafka-воркер падал на старте.
 */
final class RestProxyTransportFactoryTest extends TestCase
{
    public function testSupportsDoesNotResolvePsrServices(): void
    {
        $called = false;
        $tripwire = static function () use (&$called) {
            $called = true;

            throw new \LogicException('PSR-сервис не должен создаваться ради supports()');
        };

        $factory = new RestProxyTransportFactory(null, $tripwire, $tripwire, $tripwire, $tripwire);

        self::assertTrue($factory->supports('kafka+rest://localhost:8082', []));
        self::assertFalse($factory->supports('kafka://localhost:9092', []));

        self::assertFalse($called, 'supports() не имеет права трогать PSR-зависимости');
    }

    public function testConstructorItselfResolvesNothing(): void
    {
        $tripwire = static fn () => throw new \LogicException('создано слишком рано');

        // Сам факт создания фабрики не должен ничего инициализировать: контейнер
        // строит её на старте, до того как станет известно, нужен ли REST-proxy.
        $factory = new RestProxyTransportFactory(null, $tripwire, $tripwire, $tripwire, $tripwire);

        self::assertInstanceOf(RestProxyTransportFactory::class, $factory);
    }

    /**
     * Обратная совместимость: объекты по-прежнему можно передавать напрямую —
     * так фабрику создают вручную и в тестах.
     */
    public function testPlainObjectsAreStillAccepted(): void
    {
        $factory = new RestProxyTransportFactory(null, null, null, null, null);

        self::assertTrue($factory->supports('kafka+rest+ssl://localhost:8082', []));
    }
}
