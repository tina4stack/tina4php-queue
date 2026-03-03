<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use Exception;

interface QueueInterface
{
    /**
     * Produces a message to the queue.
     *
     * @param string $value The message content.
     * @param string|null $userId The user ID associated with the message.
     * @param callable|null $deliveryCallback Callback to handle delivery status.
     * @return QueueMessage|Exception Returns a QueueMessage object on success or an Exception on failure.
     */
    public function produce(string $value, ?string $userId, ?callable $deliveryCallback): QueueMessage|Exception;

    /**
     * Consumes messages from the queue as a generator.
     * Yields QueueMessage when a message is available, null when the queue is empty.
     *
     * @param bool $acknowledge Whether to auto-acknowledge messages.
     * @param int $batchSize Number of messages to attempt per poll cycle.
     * @return \Generator<int, QueueMessage|null, mixed, void>
     */
    public function consume(bool $acknowledge = true, int $batchSize = 1): \Generator;
}
