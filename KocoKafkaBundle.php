<?php

namespace Koco\Kafka;

use Koco\Kafka\DependencyInjection\Compiler\DecoderCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class KocoKafkaBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new DecoderCompilerPass());
    }
}
