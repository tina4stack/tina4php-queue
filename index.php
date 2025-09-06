<?php
require_once "vendor/autoload.php";

$config = new \Tina4\QueueConfig();
$config->queueType = 'litequeue';
$config->litequeueDatabaseName = 'queue.db';

$queue = new \Tina4\Queue($config, 'my-topic');

$producer = new \Tina4\Producer($queue);
$msg = $producer->produce('Hello World', 'user123');
echo "Produced message ID: {$msg->messageId}\n";

$consumer = new \Tina4\Consumer($queue, function ($consumer, $err, $msg) {
    if ($err) {
        echo "Error: $err\n";
    } else {
        echo "Consumed: {$msg->data}\n";
    }
});
$consumer->run();
