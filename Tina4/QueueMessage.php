<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

class QueueMessage {
    public readonly string $messageId;
    public readonly string $data;
    public readonly ?string $userId;
    public readonly int $status;
    public readonly int $timestamp;
    public readonly string $deliveryTag;

    public function __construct(string $messageId, string $data, ?string $userId, int $status, int $timestamp, string $deliveryTag) {
        $this->messageId = $messageId;
        $this->data = $data;
        $this->userId = $userId;
        $this->status = $status;
        $this->timestamp = $timestamp;
        $this->deliveryTag = $deliveryTag;
    }
}
