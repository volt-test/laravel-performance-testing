<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use VoltTest\Laravel\Facades\VoltTest;

class TestRunner
{
    /**
     * Run the VoltTest tests.
     * @param bool $streamOutput Whether to stream output in real-time
     * @return mixed
     */
    public function run(bool $streamOutput = false): mixed
    {
        try {
            return VoltTest::run($streamOutput);
        } catch (\Exception $e) {
            throw new \RuntimeException('Test execution failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate that tests are configured before running.
     * @throws \RuntimeException
     * @return void
     */
    public function validateTestConfiguration(): void
    {
        $scenarios = VoltTest::getScenarios();

        if ($scenarios->isEmpty()) {
            throw new \RuntimeException('No test scenarios configured. Please add test classes or URL tests.');
        }
    }
}
