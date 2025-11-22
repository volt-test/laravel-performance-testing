<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Testing;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use VoltTest\Laravel\Testing\Assertions\VoltTestAssertions;

class VoltTestAssertionsTest extends TestCase
{
    use VoltTestAssertions;

    /**
     * Test parseTimeToMs with various time formats
     */
    #[DataProvider('timeFormatProvider')]
    public function testParsesTimeFormatsCorrectly(string $input, float $expected): void
    {
        $actual = $this->parseTimeToMs($input);

        $this->assertEquals($expected, $actual, "Failed to parse '{$input}' correctly");
    }

    /**
     * Test that milliseconds are parsed correctly and not confused with minutes
     */
    public function testParsesMillisecondsNotMinutes(): void
    {
        // This is the specific case that was failing
        $result = $this->parseTimeToMs('67.365417ms');

        // Should be ~67.365ms, not ~4,041,925ms (67.365 * 60000)
        $this->assertEquals(67.365417, $result);
        $this->assertLessThan(100, $result, 'Value should be in milliseconds, not minutes');
    }

    /**
     * Test edge cases and invalid formats
     */
    public function testHandlesEdgeCases(): void
    {
        // Plain numbers should be treated as milliseconds
        $this->assertEquals(100.0, $this->parseTimeToMs('100'));
        $this->assertEquals(50.5, $this->parseTimeToMs('50.5'));

        // Zero values
        $this->assertEquals(0.0, $this->parseTimeToMs('0ms'));
        $this->assertEquals(0.0, $this->parseTimeToMs('0s'));
    }

    /**
     * Test that invalid formats throw exceptions
     */
    public function testThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unable to parse time format");

        $this->parseTimeToMs('invalid');
    }

    /**
     * Data provider for time format tests
     */
    public static function timeFormatProvider(): array
    {
        return [
            // Milliseconds
            ['100ms', 100.0],
            ['67.365417ms', 67.365417],
            ['1.5ms', 1.5],
            ['0.001ms', 0.001],

            // Seconds
            ['1s', 1000.0],
            ['2.5s', 2500.0],
            ['0.5s', 500.0],
            ['1sec', 1000.0],
            ['2second', 2000.0],
            ['3seconds', 3000.0],

            // Minutes
            ['1m', 60000.0],
            ['1min', 60000.0],
            ['2mins', 120000.0],
            ['1.5minute', 90000.0],
            ['2minutes', 120000.0],

            // Hours
            ['1h', 3600000.0],
            ['2hour', 7200000.0],
            ['1.5hours', 5400000.0],

            // Microseconds
            ['1000us', 1.0],
            ['1000µs', 1.0],
            ['500micros', 0.5],
            ['1000microsecond', 1.0],

            // Nanoseconds
            ['1000000ns', 1.0],
            ['500000ns', 0.5],

            // With spaces
            ['100 ms', 100.0],
            ['1 s', 1000.0],
            ['2 min', 120000.0],
            ['1 h', 3600000.0],

            // Plain numbers (assumed to be milliseconds)
            ['100', 100.0],
            ['250.5', 250.5],
        ];
    }
}
