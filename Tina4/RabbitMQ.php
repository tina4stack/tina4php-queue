<?php

namespace Tina4;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;

class RabbitMQ implements QueueInterface {
    private $channel;
    private string $queueName;
    private string $exchange;

    public function __construct(QueueConfig $config, string $topic) {
        $namePrefix = $config->prefix ? $config->prefix . '_' : '';
        $vhost = $config->prefix ? '/' . $config->prefix : '/';
        $connectionParams = $config->rabbitmqConfig ?? ['host' => 'localhost', 'port' => 5672, 'user' => 'guest', 'password' => 'guest'];
        $connection = new AMQPStreamConnection(
            $connectionParams['host'],
            $connectionParams['port'],
            $connectionParams['user'] ?? 'guest',
            $connectionParams['password'] ?? 'guest',
            $vhost
        );
        $this->channel = $connection->channel();
        $this->exchange = $namePrefix . $topic;
        $this->channel->exchange_declare($this->exchange, 'topic', false, false, false);
        [, $this->queueName,] = $this->channel->queue_declare($namePrefix . $topic, false, false, false, false);
        $this->channel->queue_bind($this->queueName, $this->exchange, '');
    }

    public function produce(string $value, ?string $userId, ?callable $deliveryCallback): Exception|QueueMessage
    {
        try {
            $body = [
                'message_id' => uuid7(),
                'msg' => $value,
                'user_id' => $userId,
                'in_time' => hrtime(true)
            ];
            $msg = new AMQPMessage(json_encode($body), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            $this->channel->basic_publish($msg, $this->exchange, '');
            $response = new QueueMessage($body['message_id'], $value, $userId, 0, $body['in_time'], '0');
            if ($deliveryCallback) {
                $deliveryCallback($this->channel, null, $response);
            }
            return $response;
        } catch (Exception $e) {
            if ($deliveryCallback) {
                $deliveryCallback($this->channel, $e, null);
            }
            return $e;
        }
    }

    public function consume(bool $acknowledge, ?callable $consumerCallback): void {
        try {
            $msg = $this->channel->basic_get($this->queueName, !$acknowledge);
            if ($msg) {
                $data = json_decode($msg->body, true);
                $status = $acknowledge ? 2 : 1;
                $response = new QueueMessage($data['message_id'], $data['msg'], $data['user_id'], $status, $data['in_time'], $msg->getDeliveryTag());
                if ($consumerCallback) {
                    $consumerCallback($this->channel, null, $response);
                }
            }
        } catch (Exception $e) {
            (new Debug())->error("Error consuming {$this->queueName}: " . $e->getMessage());
        }
    }
}
