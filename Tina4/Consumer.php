<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use Generator;

class Consumer {
    private array $queues;
    private bool $acknowledge;
    private float $pollInterval;

    /**
     * @param array|Queue $queues One or more Queue instances
     * @param bool $acknowledge Auto-acknowledge consumed messages
     * @param float $pollInterval Seconds between round-robin poll cycles when all queues are empty
     */
    public function __construct(array|Queue $queues, bool $acknowledge = true, float $pollInterval = 0.5) {
        $this->queues = is_array($queues) ? $queues : [$queues];
        $this->acknowledge = $acknowledge;
        $this->pollInterval = $pollInterval;
    }

    /**
     * Generator that yields QueueMessage from all queues in round-robin.
     *
     * Usage:
     *   foreach ($consumer->messages() as $msg) {
     *       echo $msg->data;
     *   }
     *
     * @return Generator<int, QueueMessage, mixed, void>
     */
    public function messages(): Generator
    {
        $generators = [];
        foreach ($this->queues as $queue) {
            $generators[] = $queue->consumeRaw($this->acknowledge);
        }

        while (true) {
            $anyMessage = false;
            foreach ($generators as $gen) {
                if (!$gen->valid()) {
                    continue;
                }
                $message = $gen->current();
                $gen->next();
                if ($message !== null) {
                    $anyMessage = true;
                    yield $message;
                }
            }
            if (!$anyMessage) {
                usleep((int)($this->pollInterval * 1_000_000));
            }
        }
    }

    /**
     * Blocking convenience method. Calls $handler for each message forever.
     *
     * Usage:
     *   $consumer->runForever(function (QueueMessage $msg) {
     *       echo $msg->data;
     *   });
     *
     * @param callable $handler function(QueueMessage $msg): void
     */
    public function runForever(callable $handler): void
    {
        foreach ($this->messages() as $message) {
            $handler($message);
        }
    }

    /**
     * @deprecated Use messages() or runForever() instead.
     */
    public function run(int $sleep = 1, ?int $iterations = null): void
    {
        @trigger_error('Consumer::run() is deprecated, use messages() or runForever()', E_USER_DEPRECATED);
        $counter = 0;
        foreach ($this->messages() as $message) {
            $counter++;
            if ($iterations !== null && $counter >= $iterations) {
                return;
            }
        }
    }
}
