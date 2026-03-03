<?php

use PHPUnit\Framework\TestCase;
use Tina4\Queue;
use Tina4\QueueConfig;
use Tina4\QueueMessage;

class QueueTest extends TestCase
{
    private QueueConfig $config;
    private string $dbFile;

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'tina4queue_') . '.db';
        $this->config = new QueueConfig();
        $this->config->litequeueDatabaseName = $this->dbFile;
        $this->config->pollInterval = 0.01;
        $this->config->maxBackoff = 0.05;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }
    }

    public function testConstructorDefaultsToLiteQueue(): void
    {
        $queue = new Queue($this->config, 'test');
        $produced = $queue->produce('hello', null);
        $this->assertInstanceOf(QueueMessage::class, $produced);
    }

    public function testConstructorWithNullConfigUsesDefaults(): void
    {
        // This will use the default 'queue.db' - just verify it doesn't throw
        $queue = new Queue(null, 'test');
        $this->assertInstanceOf(Queue::class, $queue);
    }

    public function testConsumeReturnsGenerator(): void
    {
        $queue = new Queue($this->config, 'test');
        $gen = $queue->consume();
        $this->assertInstanceOf(\Generator::class, $gen);
    }

    public function testProduceDelegatesToBackend(): void
    {
        $queue = new Queue($this->config, 'test');
        $result = $queue->produce('test data', 'user1');
        $this->assertInstanceOf(QueueMessage::class, $result);
        $this->assertEquals('test data', $result->data);
    }

    public function testInvalidQueueTypeThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported queue type');
        $config = new QueueConfig();
        $config->queueType = 'nonexistent';
        new Queue($config, 'test');
    }

    public function testGetTopic(): void
    {
        $queue = new Queue($this->config, 'my-topic');
        $this->assertEquals('my-topic', $queue->getTopic());
    }

    public function testGetConfig(): void
    {
        $queue = new Queue($this->config, 'test');
        $this->assertSame($this->config, $queue->getConfig());
    }

    public function testConsumeFiltersNullYields(): void
    {
        $queue = new Queue($this->config, 'filter_test');
        $queue->produce('real message', null);

        $messages = [];
        foreach ($queue->consume() as $msg) {
            $messages[] = $msg;
            $this->assertInstanceOf(QueueMessage::class, $msg);
            break;
        }
        $this->assertCount(1, $messages);
    }
}
