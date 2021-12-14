<?php

declare(strict_types=1);

namespace Koco\Kafka\Serializer;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

abstract class AbstractKafkaRestProxyBinarySerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        throw new \LogicException('Method not implemented yet');
    }

    public function encode(Envelope $envelope): array
    {
        $key = $this->extractKey($envelope);

        return [
            'key' => $key ? base64_encode($key) : null,
            'body' => base64_encode($this->extractBody($envelope)),
            'headers' => ['Content-Type' => 'application/vnd.kafka.binary.v2+json'],
        ];
    }

    abstract protected function extractKey(Envelope $envelope): ?string;

    abstract protected function extractBody(Envelope $envelope): string;
}
