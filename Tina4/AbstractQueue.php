<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use Exception;

abstract class AbstractQueue implements QueueInterface {
    protected QueueConfig $config;
    protected string $topic;

    /**
     * Sends the message body via the backend-specific transport.
     *
     * @param array $body The message body array.
     * @return void
     * @throws Exception If the send fails.
     */
    abstract protected function doSend(array $body): void;

    /**
     * Returns the backend-specific connection object passed to delivery callbacks.
     *
     * @return mixed
     */
    abstract protected function getConnection(): mixed;

    /**
     * Produces a message to the queue.
     *
     * @param string $value The message content.
     * @param string|null $userId The user ID associated with the message.
     * @param callable|null $deliveryCallback Callback to handle delivery status.
     * @return QueueMessage|Exception Returns a QueueMessage object on success or an Exception on failure.
     */
    public function produce(string $value, ?string $userId, ?callable $deliveryCallback): QueueMessage|Exception {
        try {
            $body = $this->buildMessage($value, $userId);
            $this->doSend($body);
            $response = $this->createQueueMessage($body, $this->topic);
            if ($deliveryCallback) {
                $deliveryCallback($this->getConnection(), null, $response);
            }
            return $response;
        } catch (Exception $e) {
            if ($deliveryCallback) {
                $deliveryCallback($this->getConnection(), $e, null);
            }
            return $e;
        }
    }

    /**
     * Builds the standard message body array.
     *
     * @param string $value The message content.
     * @param string|null $userId The user ID.
     * @return array
     */
    protected function buildMessage(string $value, ?string $userId): array {
        return [
            'message_id' => uuid7(),
            'msg' => $value,
            'user_id' => $userId,
            'in_time' => hrtime(true)
        ];
    }

    /**
     * Creates a QueueMessage response from the body array.
     *
     * @param array $body The message body.
     * @param string $topic The topic name.
     * @param string $deliveryTag The delivery tag (default '0').
     * @return QueueMessage
     */
    protected function createQueueMessage(array $body, string $topic, string $deliveryTag = '0'): QueueMessage {
        return new QueueMessage(
            $body['message_id'],
            $body['msg'],
            $body['user_id'],
            0,
            $body['in_time'],
            $deliveryTag,
            $topic
        );
    }

    /**
     * Handles exponential backoff logic for polling-based consumers.
     * When messages were found, resets backoff to the configured poll interval.
     * When no messages were found, sleeps for the current backoff duration and
     * increases it exponentially up to maxBackoff.
     *
     * The caller must yield null BEFORE calling this method when no messages are found,
     * to match the expected pattern: yield null -> sleep -> increase backoff.
     *
     * @param bool $found Whether messages were found in the current poll cycle.
     * @param float &$currentBackoff The current backoff duration in seconds (modified in place).
     * @return void
     */
    protected function handleBackoff(bool $found, float &$currentBackoff): void {
        if ($found) {
            $currentBackoff = $this->config->pollInterval;
            return;
        }

        usleep((int)($currentBackoff * 1_000_000));
        $currentBackoff = min($currentBackoff * 2, $this->config->maxBackoff);
    }
}
