<?php

declare(strict_types=1);

namespace Koco\Kafka\Transport;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/** Validates the public configuration before it reaches librdkafka. */
final class KafkaOptions
{
    public static function resolve(array $options): array
    {
        self::knownKeys($options, ['conf', 'producer', 'consumer'], 'options');
        foreach (['conf', 'producer', 'consumer'] as $section) {
            if (isset($options[$section]) && !\is_array($options[$section])) {
                throw new InvalidArgumentException($section . ' must be an array.');
            }
        }
        $producer = $options['producer'] ?? [];
        $consumer = $options['consumer'] ?? [];
        self::knownKeys($producer, ['topic_name', 'flush_timeout', 'flush_retries', 'conf'], 'producer');
        self::knownKeys($consumer, ['topics', 'receive_timeout', 'commit_async', 'conf'], 'consumer');
        $producer += ['topic_name' => null, 'flush_timeout' => 10000, 'flush_retries' => 0, 'conf' => []];
        $consumer += ['topics' => [], 'receive_timeout' => 10000, 'commit_async' => false, 'conf' => []];
        if (null !== $producer['topic_name'] && (!\is_string($producer['topic_name']) || '' === trim($producer['topic_name']))) {
            throw new InvalidArgumentException('producer.topic_name must be a non-empty string.');
        }
        if (!\is_array($consumer['topics'])) {
            throw new InvalidArgumentException('consumer.topics must be an array of non-empty topic names.');
        }
        foreach ($consumer['topics'] as $topic) {
            if (!\is_string($topic) || '' === trim($topic)) {
                throw new InvalidArgumentException('consumer.topics must contain non-empty strings.');
            }
        }
        $consumer['topics'] = array_values($consumer['topics']);
        foreach (['flush_timeout', 'flush_retries'] as $key) {
            $producer[$key] = self::integer($producer[$key], 'producer.' . $key);
        }
        $consumer['receive_timeout'] = self::integer($consumer['receive_timeout'], 'consumer.receive_timeout');
        $async = $consumer['commit_async'];
        if (!\is_bool($async) && (!\is_string($async) || !\in_array($async, ['true', 'false', '1', '0'], true))) {
            throw new InvalidArgumentException('consumer.commit_async must be a boolean.');
        }
        $consumer['commit_async'] = filter_var($async, FILTER_VALIDATE_BOOLEAN);
        $producer['conf'] = self::conf($producer['conf'], 'producer.conf');
        $consumer['conf'] = self::conf($consumer['conf'], 'consumer.conf');

        return ['conf' => self::conf($options['conf'] ?? [], 'conf'), 'producer' => $producer, 'consumer' => $consumer];
    }

    private static function knownKeys(array $options, array $allowed, string $path): void
    {
        $unknown = array_diff(array_keys($options), $allowed);
        if ($unknown) {
            throw new InvalidArgumentException(\sprintf('Unknown %s: %s. Use the 1.0 conf/producer/consumer configuration.', $path, implode(', ', $unknown)));
        }
    }

    private static function integer($value, string $path): int
    {
        if ((!\is_int($value) && !\is_string($value)) || false === filter_var($value, FILTER_VALIDATE_INT) || (int) $value < 0) {
            throw new InvalidArgumentException($path . ' must be a non-negative integer.');
        }

        return (int) $value;
    }

    private static function conf($values, string $path): array
    {
        if (!\is_array($values)) {
            throw new InvalidArgumentException($path . ' must be an array of Kafka settings.');
        }
        foreach ($values as $key => $value) {
            if (!\is_string($key) || '' === $key || !\is_scalar($value)) {
                throw new InvalidArgumentException($path . ' must map setting names to scalar values.');
            }
            $values[$key] = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $values;
    }
}
