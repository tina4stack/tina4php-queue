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

class MongoQueue implements QueueInterface {
    private $queue;
    private string $collectionName;
    private QueueConfig $config;
    private string $topic;

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

    public function produce(string $value, ?string $userId, ?callable $deliveryCallback): QueueMessage|Exception {
        try {
            $body = [
                'message_id' => uuid7(),
                'msg' => $value,
                'user_id' => $userId,
                'in_time' => hrtime(true),
                'status' => 0
            ];
            $this->queue->insertOne($body);
            $response = new QueueMessage($body['message_id'], $value, $userId, 0, $body['in_time'], '0', $this->topic);
            if ($deliveryCallback) {
                $deliveryCallback($this->queue, null, $response);
            }
            return $response;
        } catch (Exception $e) {
            if ($deliveryCallback) {
                $deliveryCallback($this->queue, $e, null);
            }
            return $e;
        }
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

            if ($found) {
                $currentBackoff = $this->config->pollInterval;
            } else {
                yield null;
                usleep((int)($currentBackoff * 1_000_000));
                $currentBackoff = min($currentBackoff * 2, $this->config->maxBackoff);
            }
        }
    }
}
