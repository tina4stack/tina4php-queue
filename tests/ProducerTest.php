<?php

use PHPUnit\Framework\TestCase;
use Tina4\Producer;
use Tina4\Queue;
use Tina4\QueueConfig;
use Tina4\QueueMessage;
use Tina4\QueueException;

class ProducerTest extends TestCase
{
    private QueueConfig $config;
    private string $dbFile;

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'tina4queue_') . '.db';
        $this->config = new QueueConfig();
        $this->config->litequeueDatabaseName = $this->dbFile;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }
    }

    public function testProduceReturnsQueueMessage(): void
    {
        $queue = new Queue($this->config, 'test');
        $producer = new Producer($queue);
        $result = $producer->produce('hello', 'user1');
        $this->assertInstanceOf(QueueMessage::class, $result);
        $this->assertEquals('hello', $result->data);
    }

    public function testProduceUsesDefaultDeliveryCallback(): void
    {
        $callbackCalled = false;
        $queue = new Queue($this->config, 'test');
        $producer = new Producer($queue, function ($db, $err, $msg) use (&$callbackCalled) {
            $callbackCalled = true;
        });
        $producer->produce('hello');
        $this->assertTrue($callbackCalled);
    }

    public function testProduceOverridesDeliveryCallback(): void
    {
        $defaultCalled = false;
        $overrideCalled = false;
        $queue = new Queue($this->config, 'test');
        $producer = new Producer($queue, function () use (&$defaultCalled) {
            $defaultCalled = true;
        });
        $producer->produce('hello', null, function () use (&$overrideCalled) {
            $overrideCalled = true;
        });
        $this->assertFalse($defaultCalled);
        $this->assertTrue($overrideCalled);
    }
}
