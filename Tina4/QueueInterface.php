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
     * @return Message|Exception Returns a Message object on success or an Exception on failure.
     */
    public function produce(string $value, ?string $userId, ?callable $deliveryCallback): QueueMessage|Exception;

    /**
     * Consumes a message from the queue.
     *
     * @param bool $acknowledge Whether to acknowledge the message.
     * @param callable|null $consumerCallback Callback to handle consumed messages.
     * @return void
     */
    public function consume(bool $acknowledge, ?callable $consumerCallback): void;
}
