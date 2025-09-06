<?php

namespace Tina4;

class Consumer {
    private array $queues; // Support multiple queues
    private $consumerCallback;
    private bool $acknowledge;

    public function __construct(array|Queue $queues, ?callable $consumerCallback = null, bool $acknowledge = true) {
        $this->queues = is_array($queues) ? $queues : [$queues];
        $this->consumerCallback = $consumerCallback;
        $this->acknowledge = $acknowledge;
    }

    public function run(int $sleep = 1, ?int $iterations = null): void {
        $counter = 0;
        while (true) {
            foreach ($this->queues as $queue) {
                $callback = $queue->callback ?? $this->consumerCallback;
                $queue->consume($this->acknowledge, $callback);
            }
            $counter++;
            if ($iterations !== null && $counter >= $iterations) break;
            sleep($sleep);
        }
    }
}
