<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

use PHPUnit\Framework\TestCase;

class InitializeTest extends TestCase
{
    public function testUuid7ReturnsValidFormat(): void
    {
        $uuid = \Tina4\uuid7();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testUuid7IsUnique(): void
    {
        $uuids = [];
        for ($i = 0; $i < 1000; $i++) {
            $uuids[] = \Tina4\uuid7();
        }
        $this->assertCount(1000, array_unique($uuids));
    }

    public function testUuid7IsMonotonicallyIncreasing(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = \Tina4\uuid7();
        }
        $sorted = $uuids;
        sort($sorted);
        $this->assertEquals($sorted, $uuids);
    }

    public function testBcDivmod(): void
    {
        [$quot, $rem] = \Tina4\bc_divmod('17', '5');
        $this->assertEquals('3', $quot);
        $this->assertEquals('2', $rem);
    }

    public function testBcLshift(): void
    {
        $result = \Tina4\bc_lshift('1', 8);
        $this->assertEquals('256', $result);
    }

    public function testBcOr(): void
    {
        $result = \Tina4\bc_or('4', '8');
        $this->assertEquals('12', $result);
    }

    public function testBcHex(): void
    {
        $result = \Tina4\bc_hex('255');
        $this->assertEquals('ff', $result);
    }

    public function testBcHexZero(): void
    {
        $result = \Tina4\bc_hex('0');
        $this->assertEquals('0', $result);
    }
}
