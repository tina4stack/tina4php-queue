<?php

namespace Tina4;

use Exception;

class Producer {
    private Queue $queue;
    private $deliveryCallback;

    public function __construct(Queue $queue, ?callable $deliveryCallback = null) {
        $this->queue = $queue;
        $this->deliveryCallback = $deliveryCallback;
    }

    public function produce(string $value, ?string $userId = null, ?callable $deliveryCallback = null): QueueMessage|Exception {
        return $this->queue->produce($value, $userId, $deliveryCallback ?? $this->deliveryCallback);
    }
}
