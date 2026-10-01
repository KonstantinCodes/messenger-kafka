# Upgrading from 0.19 to 1.0

PHP and Symfony constraints match 0.19: PHP 7.4/8.x and Symfony `^5.4 || ^6.4 || ^7.4 || ^8.1`. This is still a major release: configuration and public PHP namespaces have changed. Test your application before deploying.

## Configuration

| 0.19 | 1.0 |
| --- | --- |
| `topic.name` | `producer.topic_name` and/or `consumer.topics: [name]` |
| `flushTimeout` | `producer.flush_timeout` |
| `flushRetries` | `producer.flush_retries` |
| `receiveTimeout` | `consumer.receive_timeout` |
| `commitAsync` | `consumer.commit_async` |
| `kafka_conf` | shared `conf`, or role-specific `producer.conf` / `consumer.conf` |
| `topic_conf` | merge into the appropriate Kafka `conf` map |

The defaults remain 10000 ms for send/receive timeouts, zero additional flush attempts, and synchronous commits. Old option names now raise a configuration exception instead of being silently ignored. See the complete working example in [README.md](README.md).

Keep existing consumer group IDs and topic names during migration to preserve committed positions. Keep `enable.auto.offset.store` and `enable.auto.commit` disabled. Adding topics to a subscription changes what that consumer group processes.

Broker lists and the `kafka+ssl://` spelling remain supported. As before, configure `security.protocol` explicitly for TLS. Explicit YAML values override DSN query options.

## PHP integrations

- Move imports from `Koco\Kafka\Messenger\Kafka*` to `Koco\Kafka\Transport\Kafka*`, including `KafkaMessageStamp`.
- `Koco\Kafka\RdKafka\RdKafkaFactory` moved to `Koco\Kafka\Transport\RdKafkaFactory`.
- Sender/receiver constructors and the transport configuration changed. Update direct instantiation, custom factories, subclasses, and service decorators.
- `KafkaSenderProperties` and `KafkaReceiverProperties` no longer exist. Configure transports through `KafkaTransportFactory` where possible.
- Custom serializers receive additional topic/partition metadata and an empty array for missing Kafka headers.
- Service registration uses `src/Resources/config/services.php`. Prefer registering the bundle rather than importing internal service files.

## Native Kafka only

The 1.0 branch does not contain REST proxy transport or `AbstractKafkaRestProxyBinarySerializer`. REST proxy users must remain on 0.19 or migrate to a native Kafka connection before upgrading. Native transport now requires `ext-rdkafka` in Composer; IDE stubs are not a runtime substitute.

Sending always flushes the producer. The experimental 1.0 behavior that used `FlushBatchHandlersStamp(false)` to skip Kafka flushing has been removed; it was not a supported batching API in 0.19.
