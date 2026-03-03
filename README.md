# Tina4 Queue

A lightweight, backend-agnostic queueing system for the Tina4 PHP framework. Supports multiple backends through a unified interface, with generator-based consumption inspired by Python's `yield` pattern.

[![Tests](https://github.com/tina4stack/tina4php-queue/actions/workflows/tests.yml/badge.svg)](https://github.com/tina4stack/tina4php-queue/actions/workflows/tests.yml)

## Features

- Generator-based consumption — iterate messages with `foreach`, no polling callbacks
- Multiple backends: LiteQueue (SQLite), RabbitMQ, MongoDB, Kafka
- Multi-queue consumers with round-robin support
- Batch processing for high-throughput scenarios
- Exponential backoff on empty queues (LiteQueue, MongoDB)
- Event-driven consumption for RabbitMQ (`basic_consume`)
- UUIDv7 message IDs — sortable, unique, time-ordered
- Extensible via `QueueInterface` for adding custom backends

## Installing

```bash
composer require tina4stack/tina4php-queue
```

### Requirements

- PHP >= 8.1
- Extensions: `sqlite3`, `bcmath`

### Optional Backend Dependencies

| Backend | Composer Package |
|---------|-----------------|
| LiteQueue (SQLite) | Built-in, no extra dependencies |
| RabbitMQ | `composer require php-amqplib/php-amqplib` |
| MongoDB | `composer require mongodb/mongodb` |
| Kafka | Requires `ext-rdkafka` PHP extension |

## Quick Start

### Producing Messages

```php
$config = new \Tina4\QueueConfig();
$config->queueType = 'litequeue';
$config->litequeueDatabaseName = 'queue.db';

$queue = new \Tina4\Queue($config, 'my-topic');
$producer = new \Tina4\Producer($queue);

$msg = $producer->produce('Hello World', 'user123');
echo "Produced: {$msg->messageId}\n";
```

### Consuming Messages

Messages are consumed using PHP generators — no callbacks required.

```php
foreach ($queue->consume(acknowledge: true) as $msg) {
    echo "Received: {$msg->data} from user {$msg->userId}\n";
}
```

### Single Message Pull

```php
$gen = $queue->consume();
$msg = $gen->current(); // Get one message, or wait for one
```

## Consumer Patterns

### Multi-Queue Consumer

Listen to multiple queues simultaneously with round-robin iteration.

```php
$queue1 = new \Tina4\Queue($config, 'orders');
$queue2 = new \Tina4\Queue($config, 'notifications');

$consumer = new \Tina4\Consumer([$queue1, $queue2], acknowledge: true, pollInterval: 0.5);

foreach ($consumer->messages() as $msg) {
    echo "From {$msg->topic}: {$msg->data}\n";
}
```

### Blocking Handler

For simple workers that process messages with a callback.

```php
$consumer = new \Tina4\Consumer($queue);

$consumer->runForever(function (\Tina4\QueueMessage $msg) {
    // Process each message
    echo "Handling: {$msg->data}\n";
});
```

### Batch Processing

Process multiple messages per poll cycle for higher throughput.

```php
$queue = new \Tina4\Queue($config, 'bulk-jobs', batchSize: 10);

foreach ($queue->consume() as $msg) {
    // Up to 10 messages fetched per poll
    processJob($msg);
}
```

## Configuration

### QueueConfig Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `queueType` | string | `'litequeue'` | Backend type: `litequeue`, `rabbitmq`, `mongo-queue`, `kafka` |
| `litequeueDatabaseName` | string | `'queue.db'` | SQLite database file path |
| `rabbitmqConfig` | array\|null | `null` | RabbitMQ connection: `host`, `port`, `user`, `password` |
| `mongoQueueConfig` | array\|null | `null` | MongoDB connection: `host`, `port`, `username`, `password` |
| `kafkaConfig` | array\|null | `null` | Kafka config: `bootstrap.servers`, etc. |
| `prefix` | string | `''` | Namespace prefix for queue/topic names |
| `pollInterval` | float | `0.1` | Seconds between polls when queue is empty |
| `maxBackoff` | float | `5.0` | Maximum backoff ceiling (seconds) for exponential backoff |

### Backend Configuration Examples

**RabbitMQ**

```php
$config = new \Tina4\QueueConfig();
$config->queueType = 'rabbitmq';
$config->rabbitmqConfig = [
    'host' => 'localhost',
    'port' => 5672,
    'user' => 'guest',
    'password' => 'guest'
];
```

**MongoDB**

```php
$config = new \Tina4\QueueConfig();
$config->queueType = 'mongo-queue';
$config->mongoQueueConfig = [
    'host' => 'localhost',
    'port' => 27017,
    'username' => 'admin',
    'password' => 'secret'
];
```

**Kafka**

```php
$config = new \Tina4\QueueConfig();
$config->queueType = 'kafka';
$config->kafkaConfig = [
    'bootstrap.servers' => 'localhost:9092'
];
```

### Prefix Namespacing

Use the `prefix` property to isolate queue topics by environment.

```php
$config->prefix = 'dev';
// LiteQueue table: dev_my_topic
// RabbitMQ exchange: dev_my-topic
// MongoDB collection: dev_my-topic
```

## QueueMessage Properties

Each consumed or produced message is a `QueueMessage` with readonly properties.

| Property | Type | Description |
|----------|------|-------------|
| `messageId` | string | UUIDv7 — unique, time-sortable |
| `data` | string | The message payload |
| `userId` | string\|null | Associated user identifier |
| `status` | int | `0` pending, `1` processing, `2` acknowledged |
| `timestamp` | int | Nanosecond timestamp (`hrtime`) |
| `deliveryTag` | string | Backend-specific delivery tag |
| `topic` | string | Queue topic name (for multi-queue consumers) |

## Producer

The `Producer` wrapper provides a cleaner API that throws on failure instead of returning exceptions.

```php
$producer = new \Tina4\Producer($queue, deliveryCallback: function ($backend, $err, $msg) {
    if ($err) {
        error_log("Delivery failed: " . $err->getMessage());
    }
});

try {
    $msg = $producer->produce('payload', 'user-42');
} catch (\Tina4\QueueException $e) {
    // Handle failure
}
```

## Backend Comparison

| Feature | LiteQueue | RabbitMQ | MongoDB | Kafka |
|---------|-----------|----------|---------|-------|
| External service | None | RabbitMQ broker | MongoDB server | Kafka cluster |
| Consume strategy | Exponential backoff poll | Event-driven (`basic_consume`) | Exponential backoff poll | Timeout-based (`consume`) |
| Persistence | SQLite file | Broker (durable) | Document store | Commit log |
| Distributed | No | Yes | Yes | Yes |
| Message ordering | FIFO | FIFO | FIFO (sorted by `in_time`) | Partitioned FIFO |
| Best for | Development, low volume | Reliable messaging | Document-oriented apps | High-throughput streaming |

## Run Tests

```bash
composer test
```

All tests use LiteQueue with temporary SQLite databases — no external services required.

## License

MIT — see [LICENSE](LICENSE) for details.
