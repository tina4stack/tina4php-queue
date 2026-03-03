<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use Exception;
use Generator;

class Queue {
    private QueueConfig $config;
    private string $topic;
    private QueueInterface $backend;
    private int $batchSize;

    public function __construct(?QueueConfig $config = null, string $topic = 'default-queue', int $batchSize = 1) {
        if ($config === null) {
            $config = new QueueConfig();
        }
        $this->config = $config;
        $this->topic = $topic;
        $this->batchSize = $batchSize;

        (new Debug())->info("Initializing {$config->queueType} for topic {$topic}");

        $this->backend = $this->initBackend();
    }

    public function produce(string $value, ?string $userId = null, ?callable $deliveryCallback = null): QueueMessage|Exception {
        return $this->backend->produce($value, $userId, $deliveryCallback);
    }

    /**
     * Returns a generator that yields QueueMessage objects.
     * Null yields from the backend are filtered out; the caller only sees real messages.
     *
     * Usage:
     *   foreach ($queue->consume(acknowledge: true) as $msg) {
     *       echo $msg->data;
     *   }
     *
     * @param bool $acknowledge Auto-acknowledge messages
     * @return Generator<int, QueueMessage, mixed, void>
     */
    public function consume(bool $acknowledge = true): Generator
    {
        foreach ($this->backend->consume($acknowledge, $this->batchSize) as $msg) {
            if ($msg !== null) {
                yield $msg;
            }
        }
    }

    /**
     * Returns the raw backend generator (yields QueueMessage|null).
     * Used by Consumer for round-robin multi-queue iteration.
     *
     * @param bool $acknowledge
     * @return Generator<int, QueueMessage|null, mixed, void>
     */
    public function consumeRaw(bool $acknowledge = true): Generator
    {
        return $this->backend->consume($acknowledge, $this->batchSize);
    }

    public function getTopic(): string {
        return $this->topic;
    }

    public function getConfig(): QueueConfig {
        return $this->config;
    }

    private function initBackend(): QueueInterface {
        return match ($this->config->queueType) {
            'litequeue' => new LiteQueue($this->config, $this->topic),
            'mongo-queue' => new MongoQueue($this->config, $this->topic),
            'rabbitmq' => new RabbitMQ($this->config, $this->topic),
            'kafka' => new KafkaQueue($this->config, $this->topic),
            default => throw new Exception("Unsupported queue type: {$this->config->queueType}"),
        };
    }
}
