<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use VoltTest\TestResult;

class ReportGenerator
{
    /**
     * Display a summary of the test results.
     */
    public function displaySummary(TestResult $result, Command $command): void
    {
        $command->info('Test Results Summary:');
        $command->info('=====================');

        $this->displayMetrics($result, $command);
        $this->displayResponseTimes($result, $command);
    }

    /**
     * Display basic metrics.
     */
    protected function displayMetrics(TestResult $result, Command $command): void
    {
        $metrics = [
            'Duration' => $result->getDuration(),
            'Total Requests' => $result->getTotalRequests(),
            'Success Rate' => $result->getSuccessRate() . '%',
            'Requests per Second' => $result->getRequestsPerSecond(),
            'Success Requests' => $result->getSuccessRequests(),
            'Failed Requests' => $result->getFailedRequests(),
        ];

        foreach ($metrics as $label => $value) {
            $command->info("{$label}: {$value}");
        }
    }

    /**
     * Display response time statistics.
     */
    protected function displayResponseTimes(TestResult $result, Command $command): void
    {
        $command->info('');
        $command->info('Response Time:');
        $command->info('-------------');

        $responseTimes = [
            'Min' => $result->getMinResponseTime(),
            'Max' => $result->getMaxResponseTime(),
            'Avg' => $result->getAvgResponseTime(),
            'Median' => $result->getMedianResponseTime(),
            'P95' => $result->getP95ResponseTime(),
            'P99' => $result->getP99ResponseTime(),
        ];

        foreach ($responseTimes as $label => $value) {
            $command->info("{$label}: {$value}");
        }
    }

    /**
     * Save the test results to a report file.
     */
    public function saveReport(TestResult $result): string
    {
        $reportsPath = $this->ensureReportsDirectory();
        $filename = $this->generateReportFilename();
        $filePath = $reportsPath . '/' . $filename;

        $reportData = $this->prepareReportData($result);

        $json = json_encode($reportData, JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode report data to JSON.');
        }
        if (File::put($filePath, $json) === false) {
            throw new \RuntimeException("Failed to write report to file: {$filePath}");
        }

        return $filePath;
    }

    /**
     * Ensure the reports directory exists.
     */
    protected function ensureReportsDirectory(): string
    {
        $reportsPath = config('volttest.reports_path', storage_path('volttest/reports'));

        if (! File::exists($reportsPath)) {
            File::makeDirectory($reportsPath, 0755, true);
        }

        return $reportsPath;
    }

    /**
     * Generate a unique filename for the report.
     */
    protected function generateReportFilename(): string
    {
        $timestamp = date('Y-m-d_H-i-s');

        return "volttest_report_{$timestamp}.json";
    }

    /**
     * Prepare the report data structure.
     */
    protected function prepareReportData(TestResult $result): array
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'metadata' => [
                'generator' => 'VoltTest Laravel Package',
                'version' => '1.0.0', // You might want to get this from composer.json
            ],
            'summary' => [
                'duration' => $result->getDuration(),
                'total_requests' => $result->getTotalRequests(),
                'success_rate' => $result->getSuccessRate(),
                'requests_per_second' => $result->getRequestsPerSecond(),
                'success_requests' => $result->getSuccessRequests(),
                'failed_requests' => $result->getFailedRequests(),
            ],
            'response_times' => [
                'min' => $result->getMinResponseTime(),
                'max' => $result->getMaxResponseTime(),
                'avg' => $result->getAvgResponseTime(),
                'median' => $result->getMedianResponseTime(),
                'p95' => $result->getP95ResponseTime(),
                'p99' => $result->getP99ResponseTime(),
            ],
            'metrics' => $result->getAllMetrics(),
            'raw_output' => $result->getRawOutput(),
        ];
    }
}
