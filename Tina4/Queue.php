<?php

namespace Tina4;

use Exception;

class Queue {
    private QueueConfig $config;
    private string $topic;
    private QueueInterface $backend;
    public  $callback = null;  // Made public for access in Consumer if needed, but primarily for consume

    public function __construct(?QueueConfig $config = null, string $topic = 'default-queue', ?callable $callback = null) {
        if ($config === null) {
            $config = new QueueConfig();
        }
        $this->config = $config;
        $this->topic = $topic;
        $this->callback = $callback;

        (new Debug())->info("Initializing {$config->queueType} for topic {$topic}");

        $this->backend = $this->initBackend();
    }

    public function produce(string $value, ?string $userId = null, ?callable $deliveryCallback = null): QueueMessage|Exception {
        return $this->backend->produce($value, $userId, $deliveryCallback);
    }

    public function consume(bool $acknowledge = true, ?callable $consumerCallback = null): void {
        $this->backend->consume($acknowledge, $consumerCallback ?? $this->callback);
    }

    private function initBackend(): QueueInterface {
        switch ($this->config->queueType) {
            case 'litequeue':
                return new LiteQueue($this->config, $this->topic);
            case 'mongo-queue':
                return new MongoQueue($this->config, $this->topic);
            case 'rabbitmq':
                return new RabbitMQ($this->config, $this->topic);
            case 'kafka':
                return new KafkaQueue($this->config, $this->topic);
            default:
                throw new Exception("Unsupported queue type: {$this->config->queueType}");
        }
    }
}
