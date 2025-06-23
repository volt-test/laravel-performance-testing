<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Commands;

use Illuminate\Console\Command;
use VoltTest\Laravel\Facades\VoltTest;
use VoltTest\Laravel\Services\ReportGenerator;
use VoltTest\Laravel\Services\TestClassDiscoverer;
use VoltTest\Laravel\Services\TestConfigurationValidator;
use VoltTest\Laravel\Services\TestRunner;
use VoltTest\Laravel\Services\UrlTestCreator;

class RunVoltTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'volttest:run 
                            {test? : The test class to run OR URL to test}
                            {--path= : Path to search for test classes}
                            {--debug : Enable HTTP debugging}
                            {--users=10 : Number of virtual users}
                            {--duration= : (Optional) Test duration}
                            {--stream : Stream test output to console}
                            {--url : Treat the test argument as a URL for direct load testing}
                            {--method=GET : HTTP method for URL testing (GET, POST, PUT, DELETE)}
                            {--headers= : JSON string of headers for URL testing}
                            {--body= : Request body for URL testing (for POST/PUT)}
                            {--content-type= : Content type for URL testing}
                            {--code-status=200 : Expected HTTP status code for URL testing}
                            {--scenario-name= : Custom scenario name for URL testing}';

    /**
     * The console command description.
     */
    protected $description = 'Run VoltTest performance tests against test classes or URLs directly';

    public function __construct(
        protected TestClassDiscoverer $testDiscoverer,
        protected UrlTestCreator $urlTestCreator,
        protected TestRunner $testRunner,
        protected ReportGenerator $reportGenerator,
        protected TestConfigurationValidator $validator
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Running VoltTest...');

        try {
            $this->configureVoltTest();
            $this->setupTests();
            $result = $this->executeTests();
            $this->handleResults($result);

            $this->info('Test completed successfully.');
        } catch (\Exception $e) {
            $this->error('Error during test execution: ' . $e->getMessage());

            return;
        }
    }

    /**
     * Configure VoltTest with command options.
     */
    protected function configureVoltTest(): void
    {
        $voltTest = VoltTest::getVoltTest();

        if ($users = $this->option('users')) {
            $this->validator->validateVirtualUsers($users);
            $voltTest->setVirtualUsers((int) $users);
            $this->info("Set virtual users: {$users}");
        }

        if ($duration = $this->option('duration')) {
            $this->validator->validateDuration($duration);
            $voltTest->setDuration($duration);
            $this->info("Set test duration: {$duration}");
        }

        if ($this->option('debug')) {
            $voltTest->setHttpDebug(true);
            $this->info('HTTP debugging enabled');
        }
    }

    /**
     * Setup tests based on command arguments.
     */
    protected function setupTests(): void
    {
        $test = $this->argument('test');

        if ($this->shouldRunUrlTest($test)) {
            $this->setupUrlTest($test);
        } else {
            $this->setupClassTests();
        }
    }

    /**
     * Determine if we should run a URL test.
     */
    protected function shouldRunUrlTest(?string $test): bool
    {
        return $this->option('url') || $this->urlTestCreator->isUrl($test);
    }

    /**
     * Setup URL test.
     */
    protected function setupUrlTest(string $url): void
    {
        $this->info("Setting up direct URL test for: {$url}");

        $options = $this->getUrlTestOptions();

        // Validate URL and options
        $this->validator->validateUrl($url);
        $this->validator->validateUrlTestOptions($options);

        $testClass = $this->urlTestCreator->createUrlTest($url, $options);

        VoltTest::addTestFromClass($testClass);
        $this->info('URL test scenario created successfully.');
    }

    /**
     * Setup class-based tests.
     */
    protected function setupClassTests(): void
    {
        $testClasses = $this->getTestClasses();

        if (empty($testClasses)) {
            throw new \RuntimeException('No test classes found.');
        }

        $this->info('Found ' . count($testClasses) . ' test class(es).');

        foreach ($testClasses as $testClass) {
            $this->info("Loading test: {$testClass}");
            VoltTest::addTestFromClass($testClass);
        }
    }

    /**
     * Execute the tests.
     */
    protected function executeTests()
    {
        $this->info('Starting VoltTest performance tests...');

        $streamOutput = $this->option('stream');

        if ($streamOutput) {
            $this->info('Streaming output to console...');
        }

        return $this->testRunner->run($streamOutput);
    }

    /**
     * Handle test results.
     */
    protected function handleResults($result): void
    {
        if (! $this->option('stream')) {
            $this->reportGenerator->displaySummary($result, $this);
        }

        if (config('volttest.save_reports', true)) {
            $reportPath = $this->reportGenerator->saveReport($result);
            $this->info("Report saved to: {$reportPath}");
        }
    }

    /**
     * Get URL test options from command line.
     */
    protected function getUrlTestOptions(): array
    {
        return [
            'method' => $this->option('method') ?? 'GET',
            'scenario_name' => $this->option('scenario-name'),
            'body' => $this->option('body') ?? '',
            'content_type' => $this->option('content-type'),
            'headers' => $this->parseHeaders($this->option('headers')),
            'expected_status_code' => (int) ($this->option('code-status') ?? 200),
        ];
    }

    /**
     * Parse headers from JSON string.
     */
    protected function parseHeaders(?string $headersJson): array
    {
        if (! $headersJson) {
            return [];
        }

        try {
            $this->validator->validateJsonString($headersJson);
            $parsedHeaders = json_decode($headersJson, true, 512, JSON_THROW_ON_ERROR);

            return is_array($parsedHeaders) ? $parsedHeaders : [];
        } catch (\InvalidArgumentException|\JsonException $e) {
            $this->warn("Invalid JSON headers provided: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Get test classes to run.
     */
    protected function getTestClasses(): array
    {
        $test = $this->argument('test');

        if ($test && is_string($test) && ! $this->urlTestCreator->isUrl($test)) {
            $this->validator->validateTestClassName($test);
            $class = $this->testDiscoverer->resolveTestClass($test);

            return $class ? [$class] : [];
        }

        $searchPath = $this->option('path');
        if ($searchPath) {
            $this->validator->validatePath($searchPath);
        }

        return $this->testDiscoverer->findTestClasses($searchPath);
    }
}
