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

    /**
     * Check, if the given Kafka Message should be decoded by this decoder.
     *
     * @param Message $message
     * @return bool
     */
    public function supports(Message $message): bool;
}