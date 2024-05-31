<?php

declare(strict_types=1);

namespace Koco\Kafka\EventListener;

use RdKafka\Producer;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class FlushOnTerminate implements EventSubscriberInterface
{
    private array $producers = [];

    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::TERMINATE => ['flushWithKernel', 0],
        ];

        if (class_exists(ConsoleTerminateEvent::class)) {
            $events[ConsoleTerminateEvent::class] = ['flushWithConsole', 0];
        }

        return $events;
    }

    public function flushWithKernel(TerminateEvent $event): void
    {
        $this->flush();
    }

    public function flushWithConsole(ConsoleTerminateEvent $event): void
    {
        $this->flush();
    }

    public function addProducer(Producer $producer): void
    {
        $this->producers[] = $producer;
    }

    private function flush(): void
    {
        foreach ($this->producers as $producer) {
            $producer->flush(1000);
        }
    }
}
