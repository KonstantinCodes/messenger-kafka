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
        $decodedMessage = json_decode($message->payload, true);

        return [
            'body' => $decodedMessage['body'],
            'headers' => $decodedMessage['headers']
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