<?php

namespace Koco\Kafka\Messenger;

use \RdKafka\Message;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

final class KafkaMessageStamp implements NonSendableStampInterface
{
    /** @var Message */
    private $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}
