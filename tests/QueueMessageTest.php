<?php

use PHPUnit\Framework\TestCase;
use Tina4\QueueMessage;

class QueueMessageTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $msg = new QueueMessage('id-1', 'hello', 'user1', 0, 123456, 'tag-1', 'my-topic');
        $this->assertEquals('id-1', $msg->messageId);
        $this->assertEquals('hello', $msg->data);
        $this->assertEquals('user1', $msg->userId);
        $this->assertEquals(0, $msg->status);
        $this->assertEquals(123456, $msg->timestamp);
        $this->assertEquals('tag-1', $msg->deliveryTag);
        $this->assertEquals('my-topic', $msg->topic);
    }

    public function testNullUserId(): void
    {
        $msg = new QueueMessage('id-1', 'hello', null, 0, 123456, 'tag-1');
        $this->assertNull($msg->userId);
    }

    public function testDefaultTopicIsEmptyString(): void
    {
        $msg = new QueueMessage('id-1', 'hello', null, 0, 123456, 'tag-1');
        $this->assertEquals('', $msg->topic);
    }

    public function testTopicCanBeSetViaConstructor(): void
    {
        $msg = new QueueMessage('id-1', 'hello', null, 0, 123456, 'tag-1', 'events');
        $this->assertEquals('events', $msg->topic);
    }
}
