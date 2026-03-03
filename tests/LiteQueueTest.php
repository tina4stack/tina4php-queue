<?php

use PHPUnit\Framework\TestCase;
use Tina4\QueueConfig;
use Tina4\QueueMessage;
use Tina4\LiteQueue;

class LiteQueueTest extends TestCase
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

    public function testProduceReturnsQueueMessage(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $result = $queue->produce('hello world', 'user1', null);
        $this->assertInstanceOf(QueueMessage::class, $result);
        $this->assertEquals('hello world', $result->data);
        $this->assertEquals('user1', $result->userId);
        $this->assertEquals(0, $result->status);
        $this->assertEquals('test_topic', $result->topic);
    }

    public function testProduceWithNullUserId(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $result = $queue->produce('no user', null, null);
        $this->assertInstanceOf(QueueMessage::class, $result);
        $this->assertNull($result->userId);
    }

    public function testProduceCallsDeliveryCallback(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $callbackCalled = false;
        $callbackMsg = null;
        $queue->produce('hello', 'user1', function ($db, $err, $msg) use (&$callbackCalled, &$callbackMsg) {
            $callbackCalled = true;
            $callbackMsg = $msg;
        });
        $this->assertTrue($callbackCalled);
        $this->assertInstanceOf(QueueMessage::class, $callbackMsg);
    }

    public function testConsumeYieldsQueueMessage(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $queue->produce('message 1', 'user1', null);

        $gen = $queue->consume(true, 1);
        $msg = $gen->current();

        $this->assertInstanceOf(QueueMessage::class, $msg);
        $this->assertEquals('message 1', $msg->data);
        $this->assertEquals('user1', $msg->userId);
        $this->assertEquals('test_topic', $msg->topic);
    }

    public function testConsumeWithAcknowledgeSetsStatus2(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $queue->produce('ack me', null, null);

        $gen = $queue->consume(true, 1);
        $msg = $gen->current();

        $this->assertEquals(2, $msg->status);
    }

    public function testConsumeWithoutAcknowledgeKeepsStatus0(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $queue->produce('do not ack', null, null);

        $gen = $queue->consume(false, 1);
        $msg = $gen->current();

        $this->assertEquals(0, $msg->status);
    }

    public function testConsumeYieldsNullWhenEmpty(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $gen = $queue->consume(true, 1);
        $msg = $gen->current();

        $this->assertNull($msg);
    }

    public function testConsumeMultipleMessages(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $queue->produce('msg 1', null, null);
        $queue->produce('msg 2', null, null);
        $queue->produce('msg 3', null, null);

        $messages = [];
        $gen = $queue->consume(true, 1);
        for ($i = 0; $i < 3; $i++) {
            $msg = $gen->current();
            if ($msg !== null) {
                $messages[] = $msg->data;
            }
            $gen->next();
        }

        $this->assertEquals(['msg 1', 'msg 2', 'msg 3'], $messages);
    }

    public function testConsumeOrderIsFIFO(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $queue->produce('first', null, null);
        usleep(1000);
        $queue->produce('second', null, null);
        usleep(1000);
        $queue->produce('third', null, null);

        $gen = $queue->consume(true, 1);
        $first = $gen->current();
        $gen->next();
        $second = $gen->current();
        $gen->next();
        $third = $gen->current();

        $this->assertEquals('first', $first->data);
        $this->assertEquals('second', $second->data);
        $this->assertEquals('third', $third->data);
    }

    public function testConsumeBatchSize(): void
    {
        $queue = new LiteQueue($this->config, 'test_topic');
        $queue->produce('batch 1', null, null);
        $queue->produce('batch 2', null, null);
        $queue->produce('batch 3', null, null);

        $messages = [];
        $gen = $queue->consume(true, 3);
        for ($i = 0; $i < 3; $i++) {
            $msg = $gen->current();
            if ($msg !== null) {
                $messages[] = $msg->data;
            }
            $gen->next();
        }

        $this->assertCount(3, $messages);
        $this->assertEquals(['batch 1', 'batch 2', 'batch 3'], $messages);
    }

    public function testProduceThenConsumeRoundTrip(): void
    {
        $queue = new LiteQueue($this->config, 'roundtrip');
        $produced = $queue->produce('round trip message', 'userX', null);

        $gen = $queue->consume(true, 1);
        $consumed = $gen->current();

        $this->assertEquals($produced->messageId, $consumed->messageId);
        $this->assertEquals($produced->data, $consumed->data);
        $this->assertEquals($produced->userId, $consumed->userId);
    }

    public function testMultipleTopicsIsolated(): void
    {
        $queue1 = new LiteQueue($this->config, 'topic_a');
        $queue2 = new LiteQueue($this->config, 'topic_b');

        $queue1->produce('from topic a', null, null);
        $queue2->produce('from topic b', null, null);

        $gen1 = $queue1->consume(true, 1);
        $gen2 = $queue2->consume(true, 1);

        $msg1 = $gen1->current();
        $msg2 = $gen2->current();

        $this->assertEquals('from topic a', $msg1->data);
        $this->assertEquals('from topic b', $msg2->data);
        $this->assertEquals('topic_a', $msg1->topic);
        $this->assertEquals('topic_b', $msg2->topic);
    }

    public function testConsumeGeneratorCanBeIteratedWithForeach(): void
    {
        $queue = new LiteQueue($this->config, 'foreach_test');
        $queue->produce('foreach msg', null, null);

        $messages = [];
        foreach ($queue->consume(true, 1) as $msg) {
            if ($msg !== null) {
                $messages[] = $msg->data;
            }
            if (count($messages) >= 1) {
                break;
            }
        }

        $this->assertEquals(['foreach msg'], $messages);
    }

    public function testInvalidTopicNameThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid topic name');
        new LiteQueue($this->config, 'bad topic!@#');
    }
}
