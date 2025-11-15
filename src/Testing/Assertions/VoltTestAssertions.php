<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Testing\Assertions;

use VoltTest\TestResult;

/**
 * Trait VoltTestAssertions
 *
 * Provides assertion methods for validating VoltTest performance test results.
 * Can be used in any PHPUnit test case to add performance testing assertions.
 *
 * @package VoltTest\Laravel\Testing\Assertions
 */
trait VoltTestAssertions
{
    /**
     * Assert that the performance test was successful.
     *
     * @param TestResult $result The test result to assert against
     * @param float $minSuccessRate Minimum acceptable success rate (default: 95%)
     */
    protected function assertVTSuccessful(TestResult $result, float $minSuccessRate = 95.0): void
    {
        $successRate = $result->getSuccessRate();
        $this->assertGreaterThanOrEqual(
            $minSuccessRate,
            $successRate,
            "Performance test failed. Success rate: {$successRate}% (expected >= {$minSuccessRate}%)"
        );
    }

    /**
     * Assert that the error rate is below threshold.
     *
     * @param TestResult $result The test result to assert against
     * @param float $maxErrorRate Maximum acceptable error rate (default: 5%)
     */
    protected function assertVTErrorRate(TestResult $result, float $maxErrorRate = 5.0): void
    {
        $errorRate = 100 - $result->getSuccessRate();

        $this->assertLessThanOrEqual(
            $maxErrorRate,
            $errorRate,
            "Error rate {$errorRate}% exceeds threshold {$maxErrorRate}%"
        );
    }

    /**
     * Assert minimum response time is within expected range.
     * Useful for detecting unrealistic or cached responses.
     *
     * @param TestResult $result The test result to assert against
     * @param int $minTimeMs Minimum expected response time in milliseconds
     */
    protected function assertVTMinResponseTime(TestResult $result, int $minTimeMs): void
    {
        $minTime = $this->parseTimeToMs($result->getMinResponseTime());

        $this->assertGreaterThanOrEqual(
            $minTimeMs,
            $minTime,
            "Min response time {$minTime}ms is suspiciously low (expected >= {$minTimeMs}ms)"
        );
    }

    /**
     * Assert maximum response time is within threshold.
     * Catches worst-case scenarios that P99 might miss.
     *
     * @param TestResult $result The test result to assert against
     * @param int $maxTimeMs Maximum acceptable response time in milliseconds
     */
    protected function assertVTMaxResponseTime(TestResult $result, int $maxTimeMs): void
    {
        $maxTime = $this->parseTimeToMs($result->getMaxResponseTime());
        dump($maxTime, $result->getMaxResponseTime());

        $this->assertLessThanOrEqual(
            $maxTimeMs,
            $maxTime,
            "Max response time {$maxTime}ms exceeds threshold {$maxTimeMs}ms"
        );
    }

    /**
     * Assert average response time is within threshold.
     *
     * @param TestResult $result The test result to assert against
     * @param int $maxAvgTimeMs Maximum acceptable average response time in milliseconds
     */
    protected function assertVTAverageResponseTime(TestResult $result, int $maxAvgTimeMs): void
    {
        $avgTime = $this->parseTimeToMs($result->getAvgResponseTime());

        $this->assertLessThanOrEqual(
            $maxAvgTimeMs,
            $avgTime,
            "Average response time {$avgTime}ms exceeds threshold {$maxAvgTimeMs}ms"
        );
    }

    /**
     * Assert median (P50) response time is within threshold.
     * Represents the typical user experience.
     *
     * @param TestResult $result The test result to assert against
     * @param int $maxMedianTimeMs Maximum acceptable median response time in milliseconds
     */
    protected function assertVTMedianResponseTime(TestResult $result, int $maxMedianTimeMs): void
    {
        $medianTime = $this->parseTimeToMs($result->getMedianResponseTime());

        $this->assertLessThanOrEqual(
            $maxMedianTimeMs,
            $medianTime,
            "Median response time {$medianTime}ms exceeds threshold {$maxMedianTimeMs}ms"
        );
    }

    /**
     * Assert P95 response time is within threshold.
     * 95% of requests should complete within this time.
     *
     * @param TestResult $result The test result to assert against
     * @param int $maxP95TimeMs Maximum acceptable P95 response time in milliseconds
     */
    protected function assertVTP95ResponseTime(TestResult $result, int $maxP95TimeMs): void
    {
        $p95Time = $this->parseTimeToMs($result->getP95ResponseTime());

        $this->assertLessThanOrEqual(
            $maxP95TimeMs,
            $p95Time,
            "P95 response time {$p95Time}ms exceeds threshold {$maxP95TimeMs}ms"
        );
    }

    /**
     * Assert P99 response time is within threshold.
     * 99% of requests should complete within this time.
     *
     * @param TestResult $result The test result to assert against
     * @param int $maxP99TimeMs Maximum acceptable P99 response time in milliseconds
     */
    protected function assertVTP99ResponseTime(TestResult $result, int $maxP99TimeMs): void
    {
        $p99Time = $this->parseTimeToMs($result->getP99ResponseTime());

        $this->assertLessThanOrEqual(
            $maxP99TimeMs,
            $p99Time,
            "P99 response time {$p99Time}ms exceeds threshold {$maxP99TimeMs}ms"
        );
    }

    /**
     * Assert total requests meet minimum threshold.
     *
     * @param TestResult $result The test result to assert against
     * @param int $minRequests Minimum expected number of requests
     */
    protected function assertVTMinimumRequests(TestResult $result, int $minRequests): void
    {
        $totalRequests = $result->getTotalRequests();

        $this->assertGreaterThanOrEqual(
            $minRequests,
            $totalRequests,
            "Total requests {$totalRequests} is below minimum {$minRequests}"
        );
    }

    /**
     * Assert requests per second meet minimum threshold.
     *
     * @param TestResult $result The test result to assert against
     * @param float $minRPS Minimum expected requests per second
     */
    protected function assertVTMinimumRPS(TestResult $result, float $minRPS): void
    {
        $rps = $result->getRequestsPerSecond();

        $this->assertGreaterThanOrEqual(
            $minRPS,
            $rps,
            "Requests per second {$rps} is below minimum {$minRPS}"
        );
    }

    /**
     * Assert requests per second do not exceed maximum threshold.
     * Useful for detecting unrealistic test results.
     *
     * @param TestResult $result The test result to assert against
     * @param float $maxRPS Maximum expected requests per second
     */
    protected function assertVTMaximumRPS(TestResult $result, float $maxRPS): void
    {
        $rps = $result->getRequestsPerSecond();

        $this->assertLessThanOrEqual(
            $maxRPS,
            $rps,
            "Requests per second {$rps} exceeds maximum {$maxRPS}"
        );
    }

    /**
     * Helper function to convert time strings to milliseconds.
     * Parse time string to milliseconds.
     */
    protected function parseTimeToMs(string $time): float
    {
        $patterns = [
            '/(\d+\.?\d*)\s*h(?:ours?)?/i' => 3600000,        // hours
            '/(\d+\.?\d*)\s*ms/i' => 1,                        // milliseconds (must come before minutes!)
            '/(\d+\.?\d*)\s*(?:µs|us|micro(?:s(?:econds?)?)?)/i' => 0.001,      // microseconds (must come before minutes!)
            '/(\d+\.?\d*)\s*m(?:in(?:ute)?s?)?/i' => 60000,   // minutes
            '/(\d+\.?\d*)\s*s(?:ec(?:ond)?s?)?$/i' => 1000,   // seconds
            '/(\d+\.?\d*)\s*ns/i' => 0.000001,                 // nanoseconds
        ];

        foreach ($patterns as $pattern => $multiplier) {
            if (preg_match($pattern, $time, $matches)) {
                return (float)$matches[1] * $multiplier;
            }
        }

        // Try parsing as plain number (assume milliseconds)
        if (is_numeric($time)) {
            return (float)$time;
        }

        throw new \InvalidArgumentException(
            "Unable to parse time format: '{$time}'. " .
            "Expected formats: '100ms', '1s', '1.5m', '2h', '500us', '1000ns'"
        );
    }
}
