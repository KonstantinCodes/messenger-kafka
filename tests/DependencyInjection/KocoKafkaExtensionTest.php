<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\DependencyInjection;

use Koco\Kafka\KocoKafkaBundle;
use Koco\Kafka\Transport\KafkaTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class KocoKafkaExtensionTest extends TestCase
{
    public function testFrameworkBuildsTransportWithNewConfiguration(): void
    {
        $kernel = new class('test', false) extends Kernel {
            public function registerBundles(): iterable
            {
                return [new FrameworkBundle(), new KocoKafkaBundle()];
            }

            public function getCacheDir(): string
            {
                return sys_get_temp_dir() . '/messenger-kafka-kernel-' . getmypid();
            }

            public function getLogDir(): string
            {
                return $this->getCacheDir();
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(static function (ContainerBuilder $container): void {
                    $container->loadFromExtension('framework', ['secret' => 'test', 'http_method_override' => false, 'messenger' => [
                        'transports' => ['kafka' => ['dsn' => 'kafka://localhost:9092', 'options' => ['producer' => ['topic_name' => 'events']]]],
                    ]]);
                    $container->setAlias('test.kafka', 'messenger.transport.kafka')->setPublic(true);
                });
            }
        };
        try {
            $kernel->boot();
            self::assertInstanceOf(KafkaTransport::class, $kernel->getContainer()->get('test.kafka'));
        } finally {
            $kernel->shutdown();
            (new \Symfony\Component\Filesystem\Filesystem())->remove($kernel->getCacheDir());
        }
    }
}
