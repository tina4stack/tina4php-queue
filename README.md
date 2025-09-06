# Tina4 Queueing System

Tina4 is a PHP framework, and the current project is to implement various queuing mechanisms using the Python library as inspiration. This package provides an agnostic queueing system that supports multiple backends such as LiteQueue (SQLite-based), RabbitMQ, MongoDB, and Kafka. It abstracts the queue operations through a unified `Queue` class, allowing easy switching between backends via configuration.

## Features
- Agnostic interface for producing and consuming messages.
- Support for multiple queue types: `litequeue`, `rabbitmq`, `mongo-queue`, `kafka`.
- Time-based UUID generation (UUIDv7-inspired) for message IDs.
- Producer and Consumer classes for simplified usage.
- Extensible via `QueueInterface` for adding new backends.

## Installation

### Requirements
- PHP >= 8.1 (with PDO extension for SQLite, which is enabled by default).
- Composer for dependency management.

### Composer Dependencies
Add the following to your `composer.json`:

For runtime dependencies (based on the queue types you use):
```json
{
  "require": {
    "php-amqplib/php-amqplib": "^3.7",  // For RabbitMQ backend
    "mongodb/mongodb": "^1.21"          // For MongoDB backend
  }
}
