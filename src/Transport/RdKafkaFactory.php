<?php

namespace Koco\Kafka\Transport;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer as KafkaProducer;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
class RdKafkaFactory
{
    public function createConsumer(Conf $conf): KafkaConsumer
    {
        return new KafkaConsumer($conf);
    }

    public function createProducer(Conf $conf): KafkaProducer
    {
        return new KafkaProducer($conf);
    }
}
