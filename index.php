<?php
require_once "vendor/autoload.php";

$config = new \Tina4\QueueConfig();
$config->queueType = 'litequeue';
$config->litequeueDatabaseName = 'queue.db';

// Producer
$queue = new \Tina4\Queue($config, 'my-topic');
$producer = new \Tina4\Producer($queue);
$msg = $producer->produce('Hello World', 'user123');
echo "Produced message ID: {$msg->messageId}\n";

// Generator-based consume (single queue)
foreach ($queue->consume(acknowledge: true) as $msg) {
    echo "Consumed: {$msg->data} (user: {$msg->userId})\n";
    break; // Remove break for continuous consumption
}

// Multi-queue consumer
$queue2 = new \Tina4\Queue($config, 'other-topic');
$consumer = new \Tina4\Consumer([$queue, $queue2], acknowledge: true, pollInterval: 0.5);

// Option A: Generator iteration
foreach ($consumer->messages() as $msg) {
    echo "Received from {$msg->topic}: {$msg->data}\n";
    break; // Remove break for continuous consumption
}

// Option B: Blocking handler
// $consumer->runForever(function (\Tina4\QueueMessage $msg) {
//     echo "Handled: {$msg->data}\n";
// });
