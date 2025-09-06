<?php

namespace Tina4;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use Exception;

class KafkaQueue implements QueueInterface {
    private Producer $producer;
    private KafkaConsumer $consumer;
    private string $kafkaTopic;

    public function __construct(QueueConfig $config, string $topic) {
        $namePrefix = $config->prefix ? $config->prefix . '_' : '';
        $this->kafkaTopic = $namePrefix . $topic;
        $kafkaConf = $config->kafkaConfig ?? ['bootstrap.servers' => 'localhost:9092'];

        $conf = new Conf();
        foreach ($kafkaConf as $key => $value) {
            $conf->set($key, $value);
        }
        $conf->set('group.id', $namePrefix . 'default-queue');
        $conf->set('auto.offset.reset', 'earliest');

        $this->consumer = new KafkaConsumer($conf);
        $this->consumer->subscribe([$this->kafkaTopic]);

        $producerConf = clone $conf;
        $producerConf->set('group.id', null); // Not needed for producer
        $producerConf->set('auto.offset.reset', null);
        $this->producer = new Producer($producerConf);
    }

    public function produce(string $value, ?string $userId, ?callable $deliveryCallback): QueueMessage|Exception {
        try {
            $body = [
                'message_id' => uuid7(),
                'msg' => $value,
                'user_id' => $userId,
                'in_time' => hrtime(true)
            ];
            $topic = $this->producer->newTopic($this->kafkaTopic);
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($body));
            $this->producer->flush(1000);
            $response = new QueueMessage($body['message_id'], $value, $userId, 0, $body['in_time'], '0');
            if ($deliveryCallback) {
                $deliveryCallback($this->producer, null, $response);
            }
            return $response;
        } catch (Exception $e) {
            if ($deliveryCallback) {
                $deliveryCallback($this->producer, $e, null);
            }
            return $e;
        }
    }

    public function consume(bool $acknowledge, ?callable $consumerCallback): void {
        try {
            $msg = $this->consumer->consume(1000);
            if ($msg->err === RD_KAFKA_RESP_ERR_NO_ERROR) {
                $data = json_decode($msg->payload, true);
                $status = $acknowledge ? 2 : 1;
                $response = new QueueMessage($data['message_id'], $data['msg'], $data['user_id'], $status, $data['in_time'], (string)$msg->offset);
                if ($consumerCallback) {
                    $consumerCallback($this->consumer, null, $response);
                }
                if ($acknowledge) {
                    $this->consumer->commit();
                }
            }
        } catch (Exception $e) {
            Debug::error("Error consuming {$this->kafkaTopic}: " . $e->getMessage());
        }
    }
}
