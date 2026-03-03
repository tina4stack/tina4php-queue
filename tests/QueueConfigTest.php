<?php

use PHPUnit\Framework\TestCase;
use Tina4\QueueConfig;

class QueueConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new QueueConfig();
        $this->assertEquals('litequeue', $config->queueType);
        $this->assertEquals('queue.db', $config->litequeueDatabaseName);
        $this->assertNull($config->kafkaConfig);
        $this->assertNull($config->rabbitmqConfig);
        $this->assertNull($config->mongoQueueConfig);
        $this->assertEquals('default-queue', $config->rabbitmqQueue);
        $this->assertEquals('', $config->prefix);
    }

    public function testCustomQueueType(): void
    {
        $config = new QueueConfig();
        $config->queueType = 'rabbitmq';
        $this->assertEquals('rabbitmq', $config->queueType);
    }

    public function testPollIntervalDefault(): void
    {
        $config = new QueueConfig();
        $this->assertEquals(0.1, $config->pollInterval);
    }

    public function testMaxBackoffDefault(): void
    {
        $config = new QueueConfig();
        $this->assertEquals(5.0, $config->maxBackoff);
    }
}
