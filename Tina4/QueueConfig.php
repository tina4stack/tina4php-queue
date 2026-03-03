<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

class QueueConfig {
    public string $queueType = 'litequeue'; // Default: lite queue (SQLite-based)
    public string $litequeueDatabaseName = 'queue.db';
    public ?array $kafkaConfig = null;
    public ?array $rabbitmqConfig = null;
    public ?array $mongoQueueConfig = null;
    public string $rabbitmqQueue = 'default-queue';
    public string $prefix = '';
    public float $pollInterval = 0.1;
    public float $maxBackoff = 5.0;

    public function __construct() {}
}
