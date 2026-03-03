<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use Exception;

class Producer {
    private Queue $queue;
    private $deliveryCallback;

    public function __construct(Queue $queue, ?callable $deliveryCallback = null) {
        $this->queue = $queue;
        $this->deliveryCallback = $deliveryCallback;
    }

    /**
     * Produce a message to the queue.
     *
     * @param string $value Message payload
     * @param string|null $userId Associated user identifier
     * @param callable|null $deliveryCallback Override the default delivery callback
     * @return QueueMessage
     * @throws QueueException on failure
     */
    public function produce(string $value, ?string $userId = null, ?callable $deliveryCallback = null): QueueMessage {
        $result = $this->queue->produce($value, $userId, $deliveryCallback ?? $this->deliveryCallback);
        if ($result instanceof Exception) {
            throw new QueueException(
                "Failed to produce message: " . $result->getMessage(),
                0,
                $result
            );
        }
        return $result;
    }
}
