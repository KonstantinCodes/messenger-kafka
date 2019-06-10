# Symfony Messenger Kafka Transport

![License](https://img.shields.io/packagist/l/koco/messenger-kafka.svg)

!! This is experimental. Don't use in production. !!

At the moment, this Transport can only consume messages.

This bundle aims to provide a simple Kafka transport for Symfony Messenger.

## Installation

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
$ composer require koco/messenger-kafka
```

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require koco/messenger-kafka
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Koco\Kafka\KocoKafkaBundle::class => ['all' => true],
];
```

## Configuration

### DSN
Specify a DSN starting with either `kafka://` or  `kafka+ssl://`. There can be multiple brokers separated by `,`
* `kafka://my-local-kafka:9092`
* `kafka+ssl://my-staging-kafka:9093`
* `kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093`

### Example
```
framework:
    messenger:
        transports:
            events:
                dsn: '%env(KAFKA_URL)%'
                options:
                    commitAsync: true
                    receiveTimeout: 10000
                    topic:
                        name: "events"
                    kafka_conf:
                        group.id: 'backend-dev'
                        security.protocol: 'sasl_ssl'
                        ssl.ca.location: '%kernel.project_dir%/config/kafka/ca.pem'
                        sasl.username: '%env(KAFKA_SASL_USERNAME)%'
                        sasl.password: '%env(KAFKA_SASL_PASSWORD)%'
                        sasl.mechanisms: 'SCRAM-SHA-256'
                        max.poll.interval.ms: '45000'
                    topic_conf:
                        auto.offset.reset: 'smallest'
```
