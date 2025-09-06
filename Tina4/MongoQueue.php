<?php

namespace Tina4;

use MongoDB\Client as MongoClient;
use Exception;

class MongoQueue implements QueueInterface {
    private $queue;
    private string $collectionName;

    public function __construct(QueueConfig $config, string $topic) {
        $namePrefix = $config->prefix ? $config->prefix . '_' : '';
        $this->collectionName = $namePrefix . $topic;
        $mongoParams = $config->mongoQueueConfig ?? ['host' => 'localhost', 'port' => 27017];
        $uri = "mongodb://{$mongoParams['host']}:{$mongoParams['port']}";
        if (isset($mongoParams['username']) && isset($mongoParams['password'])) {
            $uri = "mongodb://{$mongoParams['username']}:{$mongoParams['password']}@{$mongoParams['host']}:{$mongoParams['port']}";
        }
        $client = new MongoClient($uri);
        $collection = $client->selectDatabase('queue')->selectCollection($this->collectionName);
        // Simple queue simulation; for production, use a proper queue lib or cap collection
        $this->queue = $collection;
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
            $response = new QueueMessage($body['message_id'], $value, $userId, 0, $body['in_time'], '0');
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

    public function consume(bool $acknowledge, ?callable $consumerCallback): void {
        try {
            $msg = $this->queue->findOneAndUpdate(
                ['status' => 0],
                ['$set' => ['status' => 1]],
                ['sort' => ['in_time' => 1]]
            );
            if ($msg) {
                $status = 1;
                if ($acknowledge) {
                    $this->queue->updateOne(
                        ['message_id' => $msg['message_id']],
                        ['$set' => ['status' => 2]]
                    );
                    $status = 2;
                }
                $response = new QueueMessage($msg['message_id'], $msg['msg'], $msg['user_id'], $status, $msg['in_time'], '0');
                if ($consumerCallback) {
                    $consumerCallback($this->queue, null, $response);
                }
            }
        } catch (Exception $e) {
            (new Debug())->error("Error consuming {$this->collectionName}: " . $e->getMessage());
        }
    }
}
