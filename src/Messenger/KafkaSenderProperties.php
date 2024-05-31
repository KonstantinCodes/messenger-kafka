<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use RdKafka\Conf as KafkaConf;

final class KafkaSenderProperties
{
    /** @var KafkaConf */
    private $kafkaConf;

    /** @var string */
    private $topicName;

    /** @var int */
    private $flushTimeoutMs;

    /** @var int */
    private $flushRetries;

    /** @var bool $flushOnTerminateEvent */
    private $flushOnTerminateEvent;

    public function __construct(
        KafkaConf $kafkaConf,
        string $topicName,
        int $flushTimeoutMs,
        int $flushRetries,
        bool $flushOnTerminateEvent,
    ) {
        $this->kafkaConf = $kafkaConf;
        $this->topicName = $topicName;
        $this->flushTimeoutMs = $flushTimeoutMs;
        $this->flushRetries = $flushRetries;
        $this->flushOnTerminateEvent = $flushOnTerminateEvent;
    }

    public function getKafkaConf(): KafkaConf
    {
        return $this->kafkaConf;
    }

    public function getTopicName(): string
    {
        return $this->topicName;
    }

    public function getFlushTimeoutMs(): int
    {
        return $this->flushTimeoutMs;
    }

    public function getFlushRetries(): int
    {
        return $this->flushRetries;
    }

    public function isFlushOnTerminateEvent(): bool
    {
        return $this->flushOnTerminateEvent;
    }
}
