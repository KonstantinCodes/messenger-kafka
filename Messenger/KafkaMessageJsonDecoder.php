<?php

namespace Koco\Kafka\Messenger;

use RdKafka\Message;

class KafkaMessageJsonDecoder implements KafkaMessageDecoderInterface
{
    /**
     * @inheritDoc
     */
    public function decode(Message $message): array
    {
        return [
            'body' => $message->payload,
            'headers' => $message->headers
        ];
    }

    /**
     * @inheritDoc
     */
    public function supports(Message $message): bool
    {
        return true;
    }
}
