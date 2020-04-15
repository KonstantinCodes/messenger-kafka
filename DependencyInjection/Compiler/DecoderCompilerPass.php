<?php

namespace Koco\Kafka\DependencyInjection\Compiler;

use Koco\Kafka\Messenger\KafkaTransportFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DecoderCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition(KafkaTransportFactory::class);
        $references = [];
        foreach ($container->findTaggedServiceIds('koco.messenger_kafka.decoder') as $id => $tags) {
            $references[] = new Reference($id);
        }

        $definition->setArgument(0, $references);
    }
}