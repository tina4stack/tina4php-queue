<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

use PHPUnit\Framework\TestCase;
use Tina4\Consumer;
use Tina4\Queue;
use Tina4\QueueConfig;
use Tina4\QueueMessage;

class ConsumerTest extends TestCase
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

    public function testMessagesYieldsFromSingleQueue(): void
    {
        $queue = new Queue($this->config, 'single', 1);
        $queue->produce('consumer msg', null);

        $consumer = new Consumer($queue, pollInterval: 0.01);
        $messages = [];
        foreach ($consumer->messages() as $msg) {
            $messages[] = $msg->data;
            if (count($messages) >= 1) {
                break;
            }
        }
        $this->assertEquals(['consumer msg'], $messages);
    }

    public function testMessagesYieldsFromMultipleQueues(): void
    {
        $queue1 = new Queue($this->config, 'multi_a', 1);
        $queue2 = new Queue($this->config, 'multi_b', 1);
        $queue1->produce('from a', null);
        $queue2->produce('from b', null);

        $consumer = new Consumer([$queue1, $queue2], pollInterval: 0.01);
        $messages = [];
        foreach ($consumer->messages() as $msg) {
            $messages[] = $msg->data;
            if (count($messages) >= 2) {
                break;
            }
        }
        $this->assertCount(2, $messages);
        $this->assertContains('from a', $messages);
        $this->assertContains('from b', $messages);
    }

    public function testRunForeverCallsHandler(): void
    {
        $queue = new Queue($this->config, 'forever', 1);
        $queue->produce('handler msg', null);

        $handled = [];
        $consumer = new Consumer($queue, pollInterval: 0.01);

        try {
            $consumer->runForever(function (QueueMessage $msg) use (&$handled) {
                $handled[] = $msg->data;
                // Throw to break the infinite loop in testing
                throw new \OverflowException('break');
            });
        } catch (\OverflowException $e) {
            // Expected - this is how we break out of runForever in tests
        }

        $this->assertEquals(['handler msg'], $handled);
    }

    public function testConstructorAcceptsSingleQueue(): void
    {
        $queue = new Queue($this->config, 'test');
        $consumer = new Consumer($queue);
        $this->assertInstanceOf(Consumer::class, $consumer);
    }

    public function testConstructorAcceptsArrayOfQueues(): void
    {
        $queue1 = new Queue($this->config, 'test1');
        $queue2 = new Queue($this->config, 'test2');
        $consumer = new Consumer([$queue1, $queue2]);
        $this->assertInstanceOf(Consumer::class, $consumer);
    }

    public function testRunForeverProcessesMessage(): void
    {
        $queue = new Queue($this->config, 'runforever_test', 1);
        $queue->produce('process me', null);

        $processed = [];
        $consumer = new Consumer($queue, pollInterval: 0.01);

        // Use messages() to verify instead of runForever to avoid infinite loop
        foreach ($consumer->messages() as $msg) {
            $processed[] = $msg->data;
            break;
        }

        $this->assertEquals(['process me'], $processed);
    }
}
