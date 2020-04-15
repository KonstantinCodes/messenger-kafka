<?php

namespace Koco\Kafka\Messenger;

use RdKafka\Message;

class KafkaMessageDecoder
{
    /** @var KafkaMessageDecoderInterface[] */
    private $decoders;

    public function __construct(array $decoders)
    {
        $this->decoders = $decoders;
    }

    public function decode(Message $message)
    {
        foreach ($this->decoders as $decoder) {
            if ($decoder->supports($message)) {
                return $decoder->decode($message);
            }
        }

        return (new KafkaMessageJsonDecoder())->decode($message);
    }
}