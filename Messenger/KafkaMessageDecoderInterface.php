<?php

namespace Koco\Kafka\Messenger;

use RdKafka\Message;

interface KafkaMessageDecoderInterface
{
    /**
     * The returned array will be passed into
     * \Symfony\Component\Messenger\Transport\Serialization\SerializerInterface::decode
     *
     * Necessary keys are "body" and "headers".
     *
     * @param Message $message
     * @return array
     */
    public function decode(Message $message): array;
}