<?php

namespace Koco\Kafka\Tests\Messenger;

use Koco\Kafka\KocoKafkaBundle;
use Koco\Kafka\Messenger\KafkaTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Messenger\MessageBusInterface;

class ServiceTest extends TestCase
{
    public function testServiceWiring()
    {
        $kernel = new KocoKafkaBundleTestingKernel([
            'transports' => [
                'producer' => [
                    'dsn' => 'kafka://my-local-kafka:9092',
                    'options' => [
                        'flushTimeout' => 10000,
                        'topic' => [
                            'name' => 'kafka'
                        ],
                        'kafka_config' => [

                        ]
                    ]
                ]
            ]
        ]);

        $kernel->boot();

        $container = $kernel->getContainer();

        /** @var KafkaTransportFactory $kocoKafka */
        /*$kocoKafka = $container->get(KafkaTransportFactory::class);

        $this->assertInstanceOf(KafkaTransportFactory::class, $kocoKafka);

        $this->assertTrue($kocoKafka->supports('kafka://my-local-kafka:9092', []));*/
    }
}

class KocoKafkaBundleTestingKernel extends Kernel
{
    /** @var array */
    private $messengerConfig = [];

    public function __construct(array $messengerConfig)
    {
        $this->messengerConfig = ['messenger' => $messengerConfig];

        parent::__construct('test', true);
    }

    public function registerBundles()
    {
        return [
            new KocoKafkaBundle(),
            new FrameworkBundle()
        ];
    }

    /**
     * @inheritDoc
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function(ContainerBuilder $container) {
            $container->loadFromExtension('framework', $this->messengerConfig);
        });
    }
}

class TestMessage
{
    public $data;
}