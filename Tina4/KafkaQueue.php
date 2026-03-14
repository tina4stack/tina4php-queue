<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer as KafkaProducer;
use Exception;
use Generator;

class KafkaQueue extends AbstractQueue {
    private KafkaProducer $producer;
    private KafkaConsumer $consumer;
    private string $kafkaTopic;

    public function __construct(QueueConfig $config, string $topic) {
        $this->config = $config;
        $this->topic = $topic;
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
        $producerConf->set('group.id', null);
        $producerConf->set('auto.offset.reset', null);
        $this->producer = new KafkaProducer($producerConf);
    }

    protected function getConnection(): mixed {
        return $this->producer;
    }

    /**
     * Sends a message to Kafka.
     *
     * @param array $body
     * @return void
     */
    protected function doSend(array $body): void {
        $topic = $this->producer->newTopic($this->kafkaTopic);
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, json_encode($body));
        $this->producer->flush(1000);
    }

    /**
     * Generator that yields messages from Kafka.
     * Kafka's consume() blocks with a timeout, so no additional backoff needed.
     *
     * @param bool $acknowledge
     * @param int $batchSize
     * @return Generator<int, QueueMessage|null, mixed, void>
     */
    public function consume(bool $acknowledge = true, int $batchSize = 1): Generator
    {
        $timeoutMs = max((int)($this->config->pollInterval * 1000), 100);

        while (true) {
            try {
                $msg = $this->consumer->consume($timeoutMs);
                switch ($msg->err) {
                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                        $data = json_decode($msg->payload, true);
                        $status = $acknowledge ? 2 : 1;
                        $response = new QueueMessage(
                            $data['message_id'],
                            $data['msg'],
                            $data['user_id'] ?? null,
                            $status,
                            $data['in_time'],
                            (string)$msg->offset,
                            $this->topic
                        );
                        if ($acknowledge) {
                            $this->consumer->commit($msg);
                        }
                        yield $response;
                        break;
                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                        yield null;
                        break;
                    default:
                        (new Debug())->error("Kafka consume error on {$this->kafkaTopic}: " . $msg->errstr());
                        yield null;
                        break;
                }
            } catch (Exception $e) {
                (new Debug())->error("Error consuming {$this->kafkaTopic}: " . $e->getMessage());
                yield null;
            }
        }
    }
}
