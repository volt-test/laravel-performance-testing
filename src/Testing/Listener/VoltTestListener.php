<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Testing\Listener;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

class VoltTestListener implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        $result = \VoltTest\Laravel\Testing\PerformanceTestCase::getLastTestResult();

        if ($result) {
            $this->printResult($event->test()->id(), $result);
            \VoltTest\Laravel\Testing\PerformanceTestCase::clearLastTestResult();
        }
    }

    /**
     * Print the performance result to the console.
     */
    protected function printResult(string $testName, mixed $result): void
    {
        // Check if result has the expected methods (duck typing)
        if (! method_exists($result, 'getTotalRequests')) {
            return;
        }

        $output = "\n\n";
        $output .= "----------------------------------------------------------------------\n";
        $output .= "Performance Report: " . $this->shortenTestName($testName) . "\n";
        $output .= "----------------------------------------------------------------------\n";
        $output .= sprintf("Total Requests:      %d\n", $result->getTotalRequests());
        $output .= sprintf("Success Rate:        %.2f%%\n", $result->getSuccessRate());
        $output .= sprintf("Requests/Sec (RPS):  %.2f\n", $result->getRequestsPerSecond());
        $output .= sprintf("Avg Latency:         %s\n", $result->getAvgResponseTime());
        $output .= sprintf("P95 Latency:         %s\n", $result->getP95ResponseTime());
        $output .= sprintf("P99 Latency:         %s\n", $result->getP99ResponseTime());
        $output .= "----------------------------------------------------------------------\n";

        fwrite(STDOUT, $output);
    }

    /**
     * Shorten the test name for display.
     */
    protected function shortenTestName(string $testName): string
    {
        // Extract method name if possible
        if (preg_match('/::(\w+)/', $testName, $matches)) {
            return $matches[1];
        }
        return $testName;
    }
}
