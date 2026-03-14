<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;
use Generator;
use SplQueue;

class RabbitMQ extends AbstractQueue {
    private $channel;
    private string $queueName;
    private string $exchange;

    public function __construct(QueueConfig $config, string $topic) {
        $this->config = $config;
        $this->topic = $topic;
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

    protected function getConnection(): mixed {
        return $this->channel;
    }

    /**
     * Sends a message to RabbitMQ.
     *
     * @param array $body
     * @return void
     */
    protected function doSend(array $body): void {
        $msg = new AMQPMessage(json_encode($body), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $this->channel->basic_publish($msg, $this->exchange, '');
    }

    /**
     * Generator that yields messages from RabbitMQ using event-driven basic_consume.
     *
     * @param bool $acknowledge
     * @param int $batchSize
     * @return Generator<int, QueueMessage|null, mixed, void>
     */
    public function consume(bool $acknowledge = true, int $batchSize = 1): Generator
    {
        $buffer = new SplQueue();
        $topic = $this->topic;

        $this->channel->basic_qos(null, $batchSize, null);
        $this->channel->basic_consume(
            $this->queueName,
            '',
            false,
            !$acknowledge,
            false,
            false,
            function (AMQPMessage $amqpMsg) use ($buffer, $acknowledge, $topic) {
                $data = json_decode($amqpMsg->body, true);
                $status = $acknowledge ? 2 : 1;
                $message = new QueueMessage(
                    $data['message_id'],
                    $data['msg'],
                    $data['user_id'] ?? null,
                    $status,
                    $data['in_time'],
                    (string)$amqpMsg->getDeliveryTag(),
                    $topic
                );
                $buffer->enqueue(['message' => $message, 'amqpMsg' => $amqpMsg]);
            }
        );

        while ($this->channel->is_consuming()) {
            try {
                $this->channel->wait(null, true);
            } catch (Exception $e) {
                (new Debug())->error("Error consuming {$this->queueName}: " . $e->getMessage());
            }

            if ($buffer->isEmpty()) {
                yield null;
            } else {
                while (!$buffer->isEmpty()) {
                    $item = $buffer->dequeue();
                    if ($acknowledge) {
                        $this->channel->basic_ack($item['amqpMsg']->getDeliveryTag());
                    }
                    yield $item['message'];
                }
            }
        }
    }
}
