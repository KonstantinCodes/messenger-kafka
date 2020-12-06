# Symfony Messenger Kafka Transport

[![License](https://img.shields.io/github/license/KonstantinCodes/messenger-kafka.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/koco/messenger-kafka.svg)](https://packagist.org/packages/koco/messenger-kafka)
[![Maintainability](https://api.codeclimate.com/v1/badges/7fa3d2da6a828a676f35/maintainability)](https://codeclimate.com/github/KonstantinCodes/messenger-kafka/maintainability)
[![CircleCI](https://circleci.com/gh/KonstantinCodes/messenger-kafka.svg?style=svg)](https://circleci.com/gh/KonstantinCodes/messenger-kafka)
[![Tests](https://github.com/KonstantinCodes/messenger-kafka/workflows/Tests/badge.svg)](https://github.com/KonstantinCodes/messenger-kafka/actions)

This bundle aims to provide a simple Kafka transport for Symfony Messenger. Kafka REST Proxy support coming soon.

## Installation

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
$ composer require koco/messenger-kafka
```

### Applications that don't use Symfony Flex

After adding the composer requirement, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
return [
    // ...
    Koco\Kafka\KocoKafkaBundle::class => ['all' => true],
];
```

## Configuration

### DSN
Specify a DSN starting with either `kafka://` or  `kafka+ssl://`. Multiple brokers are separated by `,`.
* `kafka://my-local-kafka:9092`
* `kafka+ssl://my-staging-kafka:9093`
* `kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-02:9093,kafka+ssl://prod-kafka-03:9093`

### Example
The configuration options for `kafka_conf` and `topic_conf` can be found [here](https://github.com/edenhill/librdkafka/blob/master/CONFIGURATION.md).
It is highly recommended to set `enable.auto.offset.store` to `false` for consumers. Otherwise, every message will be acknowledged, regardless of any error thrown by the message handlers.

```yaml
framework:
    messenger:
        transports:
            producer:
                dsn: '%env(KAFKA_URL)%'
                # serializer: App\Infrastructure\Messenger\MySerializer
                options:
                    flushTimeout: 10000
                    flushRetries: 5
                    topic:
                        name: 'events'
                    kafka_conf:
                        security.protocol: 'sasl_ssl'
                        ssl.ca.location: '%kernel.project_dir%/config/kafka/ca.pem'
                        sasl.username: '%env(KAFKA_SASL_USERNAME)%'
                        sasl.password: '%env(KAFKA_SASL_PASSWORD)%'
                        sasl.mechanisms: 'SCRAM-SHA-256'
            consumer:
                dsn: '%env(KAFKA_URL)%'
                # serializer: App\Infrastructure\Messenger\MySerializer
                options:
                    commitAsync: true
                    receiveTimeout: 10000
                    topic:
                        name: "events"
                    kafka_conf:
                        enable.auto.offset.store: 'false'
                        group.id: 'my-group-id' # should be unique per consumer
                        security.protocol: 'sasl_ssl'
                        ssl.ca.location: '%kernel.project_dir%/config/kafka/ca.pem'
                        sasl.username: '%env(KAFKA_SASL_USERNAME)%'
                        sasl.password: '%env(KAFKA_SASL_PASSWORD)%'
                        sasl.mechanisms: 'SCRAM-SHA-256'
                        max.poll.interval.ms: '45000'
                    topic_conf:
                        auto.offset.reset: 'earliest'
```

## Message Formats
You will most likely want to implement your own Serializer.
Please see: [https://symfony.com/doc/current/messenger.html#serializing-messages](https://symfony.com/doc/current/messenger.html#serializing-messages)

```php
<?php
namespace App\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class MySerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        // ...
    }

    public function encode(Envelope $envelope): array
    {
        // ...
    }

}
```

### How do I work with Avro?
Same as with the basic example above, you need to build your own serializer.
Within the `decode()` and `encode()` you can make use of [flix-tech/avro-serde-php](https://github.com/flix-tech/avro-serde-php).