# Symfony Messenger Kafka Transport

[![License](https://img.shields.io/github/license/KonstantinCodes/messenger-kafka.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/dt/koco/messenger-kafka.svg)](https://packagist.org/packages/koco/messenger-kafka)
[![Tests](https://github.com/KonstantinCodes/messenger-kafka/actions/workflows/php.yml/badge.svg?branch=1.0)](https://github.com/KonstantinCodes/messenger-kafka/actions/workflows/php.yml?query=branch%3A1.0)

A native Kafka transport for Symfony Messenger. The 1.0 branch supports PHP 7.4 and PHP 8.x, with Symfony `^5.4 || ^6.4 || ^7.4 || ^8.1`. Each Symfony version still requires its own minimum PHP version. The `rdkafka` extension (4.x, 5.x, or 6.x) is required.

Upgrading from 0.19? Read [UPGRADE-1.0.md](UPGRADE-1.0.md) before changing your Composer constraint. The 1.0 configuration and PHP namespaces have changed; REST proxy transport is not included.

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

Use `kafka://` or `kafka+ssl://`, with comma-separated brokers. A missing port defaults to 9092. Both a single scheme and a scheme on each broker are accepted:

```text
kafka://localhost:9092
kafka://broker-1:9092,broker-2:9092
kafka+ssl://broker-1:9093,kafka+ssl://broker-2:9093
```

The `kafka+ssl://` spelling is retained for compatibility; configure TLS explicitly with `security.protocol: ssl` or `sasl_ssl` and the relevant certificates/credentials. Credentials belong in Kafka configuration, not in the DSN.

### Example

Shared librdkafka settings go in `conf`. Settings under `producer.conf` or `consumer.conf` override shared values for that role. Broker addresses come from the DSN; role-specific Kafka settings can override them if needed.

```yaml
framework:
    messenger:
        transports:
            kafka:
                dsn: '%env(KAFKA_URL)%'
                # serializer: App\Infrastructure\Messenger\MySerializer
                options:
                    conf:
                        security.protocol: sasl_ssl
                        ssl.ca.location: '%kernel.project_dir%/config/kafka/ca.pem'
                        sasl.username: '%env(KAFKA_SASL_USERNAME)%'
                        sasl.password: '%env(KAFKA_SASL_PASSWORD)%'
                        sasl.mechanisms: SCRAM-SHA-256
                    producer:
                        topic_name: events
                        flush_timeout: 10000
                        flush_retries: 5
                    consumer:
                        topics: [events, notifications]
                        receive_timeout: 10000
                        commit_async: false
                        conf:
                            group.id: my-app
                            enable.auto.offset.store: 'false'
                            enable.auto.commit: 'false'
                            auto.offset.reset: earliest
        routing:
            'App\Message\Event': kafka
```

A producer-only transport may omit `consumer`; a consumer-only transport may omit `producer`. Sending requires `producer.topic_name`; receiving requires at least one entry in `consumer.topics`.

| Option | Default | Meaning |
| --- | --- | --- |
| `producer.flush_timeout` | `10000` | Timeout per flush attempt, in milliseconds |
| `producer.flush_retries` | `0` | Additional attempts after the first flush |
| `consumer.receive_timeout` | `10000` | Timeout per poll, in milliseconds |
| `consumer.commit_async` | `false` | Commit asynchronously when Messenger acknowledges a message |

Timeouts and retry counts must be non-negative integers. Unknown options are rejected. Kafka configuration maps accept scalar values; boolean values are converted to `true`/`false` strings.

Disable **both** automatic offset storage and automatic commits as shown above so that Messenger controls acknowledgements. Kafka settings otherwise use librdkafka defaults. A rejected message is not committed by this transport; application handlers should be idempotent because redelivery is possible.

DSN query parameters can also provide nested options, for example `?consumer[topics][]=events&consumer[receive_timeout]=1000`. Explicit YAML options override query values; topic lists are replaced as a whole. See [librdkafka configuration](https://github.com/confluentinc/librdkafka/blob/master/CONFIGURATION.md) for Kafka settings.

Each send polls and flushes the producer before returning; flush failures raise a transport exception. `FlushBatchHandlersStamp` controls Symfony batch handlers and does not disable Kafka flushing.

## Development

```sh
composer update
composer lint
composer test -- --exclude-group integration
MESSENGER_KAFKA_DSN=kafka://localhost:9092 composer test
```

Run coding style checks on PHP 7.4, the lowest supported version. Integration tests require a reachable Kafka broker; without the environment variable they are skipped. GitHub Actions supplies a real Kafka broker for every compatibility job and uploads JUnit and coverage reports, including on failure.

The build covers PHP 7.4/8.0 with Symfony 5.4, PHP 8.1 with Symfony 6.4, PHP 8.2/8.3 with Symfony 7.4, and PHP 8.4/8.5 with Symfony 8.1.

## Serializer
You will most likely want to implement your own Serializer.
Please see: [https://symfony.com/doc/current/messenger.html#serializing-messages](https://symfony.com/doc/current/messenger.html#serializing-messages)

Encoding accepts `body` plus optional `key`, `headers`, and `timestamp_ms`. Decoding receives `body`, `headers` (always an array), `key`, `topic_name`, `partition`, `offset`, and `timestamp`.

```php
<?php
namespace App\Infrastructure\Messenger;

use App\Catalogue\Domain\Model\Event\ProductCreated;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class MySerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        $record = json_decode($encodedEnvelope['body'], true);

        return new Envelope(new ProductCreated(
            $record['id'],
            $record['name'],
            $record['description'],
        ));
    }

    public function encode(Envelope $envelope): array
    {
        /** @var ProductCreated $event */
        $event = $envelope->getMessage();
        
        return [
            'key' => $event->getId(),
            'headers' => [],
            'body' => json_encode([
                'id' => $event->getId(),
                'name' => $event->getName(),
                'description' => $event->getDescription(),
            ]),
        ];
    }

}
```

## How do I work with Avro?
Same as with the basic example above, you need to build your own serializer.
Within the `decode()` and `encode()` you can make use of [flix-tech/avro-serde-php](https://github.com/flix-tech/avro-serde-php).

## What about the Confluent Schema Registry?
To connect with Schema Registry and control various settings, you can use this bundle:

```console
$ composer require koco/avro-regy
```

And configure it to match your setup:

```yaml
avro_regy:
  base_uri: '%env(SCHEMA_REGISTRY_URL)%'
  file_naming_strategy: subject
  options:
    register_missing_schemas: true
    register_missing_subjects: true
  serializers:
    catalogue:
      schema_dir: '%kernel.project_dir%/src/Catalogue/Domain/Model/Event/Avro/'
    orders:
      schema_dir: '%kernel.project_dir%/src/Orders/Domain/Model/Event/Avro/'
      file_naming_strategy: qualified_name
      options:
        register_missing_schemas: false
        register_missing_subjects: false
```

Please see [https://github.com/KonstantinCodes/avro-regy](https://github.com/KonstantinCodes/avro-regy) for the full documentation.
