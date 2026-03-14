<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use MongoDB\Client as MongoClient;
use Exception;
use Generator;

class MongoQueue extends AbstractQueue {
    private $queue;
    private string $collectionName;

    public function __construct(QueueConfig $config, string $topic) {
        $this->config = $config;
        $this->topic = $topic;
        $namePrefix = $config->prefix ? $config->prefix . '_' : '';
        $this->collectionName = $namePrefix . $topic;
        $mongoParams = $config->mongoQueueConfig ?? ['host' => 'localhost', 'port' => 27017];
        $uri = "mongodb://{$mongoParams['host']}:{$mongoParams['port']}";
        if (isset($mongoParams['username']) && isset($mongoParams['password'])) {
            $uri = "mongodb://{$mongoParams['username']}:{$mongoParams['password']}@{$mongoParams['host']}:{$mongoParams['port']}";
        }
        $client = new MongoClient($uri);
        $this->queue = $client->selectDatabase('queue')->selectCollection($this->collectionName);
    }

    protected function getConnection(): mixed {
        return $this->queue;
    }

    /**
     * Sends a message to MongoDB.
     * Adds the 'status' field required by MongoDB storage.
     *
     * @param array $body
     * @return void
     */
    protected function doSend(array $body): void {
        $body['status'] = 0;
        $this->queue->insertOne($body);
    }

    /**
     * Generator that yields messages from MongoDB.
     * Uses exponential backoff when queue is empty.
     *
     * @param bool $acknowledge
     * @param int $batchSize
     * @return Generator<int, QueueMessage|null, mixed, void>
     */
    public function consume(bool $acknowledge = true, int $batchSize = 1): Generator
    {
        $currentBackoff = $this->config->pollInterval;

        while (true) {
            $found = false;
            try {
                for ($i = 0; $i < $batchSize; $i++) {
                    $msg = $this->queue->findOneAndUpdate(
                        ['status' => 0],
                        ['$set' => ['status' => 1]],
                        ['sort' => ['in_time' => 1]]
                    );
                    if (!$msg) {
                        break;
                    }

                    $found = true;
                    $status = 1;
                    if ($acknowledge) {
                        $this->queue->updateOne(
                            ['message_id' => $msg['message_id']],
                            ['$set' => ['status' => 2]]
                        );
                        $status = 2;
                    }
                    yield new QueueMessage(
                        $msg['message_id'],
                        $msg['msg'],
                        $msg['user_id'] ?? null,
                        $status,
                        $msg['in_time'],
                        '0',
                        $this->topic
                    );
                }
            } catch (Exception $e) {
                (new Debug())->error("Error consuming {$this->collectionName}: " . $e->getMessage());
            }

            if (!$found) {
                yield null;
            }
            $this->handleBackoff($found, $currentBackoff);
        }
    }
}
