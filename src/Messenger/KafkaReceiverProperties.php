<?php

declare(strict_types=1);

namespace Koco\Kafka\Messenger;

use RdKafka\Conf as KafkaConf;

final class KafkaReceiverProperties
{
    /** @var KafkaConf */
    private $kafkaConf;

    /** @var string */
    private $topicName;

    /** @var int */
    private $receiveTimeoutMs;

    /** @var bool */
    private $commitAsync;

    public function __construct(
        KafkaConf $kafkaConf,
        string $topicName,
        int $receiveTimeoutMs,
        bool $commitAsync
    ) {
        $this->kafkaConf = $kafkaConf;
        $this->topicName = $topicName;
        $this->receiveTimeoutMs = $receiveTimeoutMs;
        $this->commitAsync = $commitAsync;
    }

    public function getKafkaConf(): KafkaConf
    {
        return $this->kafkaConf;
    }

    public function getTopicName(): string
    {
        return $this->topicName;
    }

    public function getReceiveTimeoutMs(): int
    {
        return $this->receiveTimeoutMs;
    }

    public function isCommitAsync(): bool
    {
        return $this->commitAsync;
    }
}
