<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

use PHPUnit\Framework\TestCase;
use Tina4\QueueException;

class QueueExceptionTest extends TestCase
{
    public function testIsRuntimeException(): void
    {
        $e = new QueueException("test");
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testMessagePreserved(): void
    {
        $e = new QueueException("something went wrong");
        $this->assertEquals("something went wrong", $e->getMessage());
    }

    public function testPreviousExceptionChained(): void
    {
        $previous = new \Exception("root cause");
        $e = new QueueException("wrapper", 0, $previous);
        $this->assertSame($previous, $e->getPrevious());
    }
}
