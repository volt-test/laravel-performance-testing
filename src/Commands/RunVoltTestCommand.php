<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Commands;

use Illuminate\Console\Command;
use VoltTest\CloudRun;
use VoltTest\Laravel\Facades\VoltTest;
use VoltTest\Laravel\Services\ReportGenerator;
use VoltTest\Laravel\Services\TestClassDiscoverer;
use VoltTest\Laravel\Services\TestConfigurationValidator;
use VoltTest\Laravel\Services\TestRunner;
use VoltTest\Laravel\Services\UrlTestCreator;
use VoltTest\TestResult;

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
                            {--scenario-name= : Custom scenario name for URL testing}
                            {--cloud : Run test on VoltTest Cloud}
                            {--stage=* : Load stages as duration:target (e.g. --stage=1m:50 --stage=5m:100 --stage=1m:0)}';

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

            if ($result === null) {
                $this->info('Test run cancelled.');

                return;
            }

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

        $stages = $this->option('stage');
        if (is_array($stages) && count($stages) > 0) {
            VoltTest::resetLoadProfile();
            $voltTest = VoltTest::getVoltTest();
            foreach ($stages as $stageStr) {
                [$duration, $target] = $this->parseStageOption($stageStr);
                $voltTest->stage($duration, $target);
            }
            $this->info('Configured ' . count($stages) . ' stage(s)');
        } else {
            $users = $this->option('users');
            if (is_string($users)) {
                $this->validator->validateVirtualUsers($users);
                $voltTest->setVirtualUsers((int) $users);
                $this->info("Set virtual users: {$users}");
            }

            $duration = $this->option('duration');
            if ($duration && is_string($duration)) {
                $this->validator->validateDuration($duration);
                $voltTest->setDuration($duration);
                $this->info("Set test duration: {$duration}");
            }
        }

        if ($this->option('debug')) {
            $voltTest->setHttpDebug(true);
            $this->info('HTTP debugging enabled');
        }

        if ($this->option('cloud') || config('volttest.cloud.enabled', false)) {
            VoltTest::cloud();
            $this->info('Cloud execution mode enabled.');

            VoltTest::setOnConflictPrompt(function (array $existingTests) {
                if (! $this->input->isInteractive() || empty($existingTests)) {
                    return $existingTests[0]['id'] ?? null;
                }

                $count = count($existingTests);
                $name = $existingTests[0]['name'] ?? 'Unknown';
                $this->warn("{$count} test(s) named '{$name}' already exist:");

                $options = [];
                foreach ($existingTests as $test) {
                    $id = substr($test['id'] ?? '', 0, 8);
                    $url = $test['target_url'] ?? 'N/A';
                    $vus = $test['virtual_users'] ?? '?';
                    $updated = $test['updated_at'] ?? '';
                    $options[] = "Update {$id}...  Target: {$url}  VUs: {$vus}  Updated: {$updated}";
                }
                $options[] = 'Create new test';
                $options[] = 'Cancel';

                $choice = $this->choice('What would you like to do?', $options, 0);

                if ($choice === 'Cancel') {
                    return 'cancel';
                }

                if ($choice === 'Create new test') {
                    return null;
                }

                $index = array_search($choice, $options);

                return $existingTests[$index]['id'] ?? null;
            });
        }
    }

    /**
     * Parse a stage option string (e.g. "1m:50") into duration and target.
     *
     * @return array{0: string, 1: int}
     */
    protected function parseStageOption(string $stage): array
    {
        $parts = explode(':', $stage);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                "Invalid stage format '{$stage}'. Use duration:target (e.g. 1m:50)"
            );
        }

        return [$parts[0], (int) $parts[1]];
    }

    /**
     * Setup tests based on command arguments.
     */
    protected function setupTests(): void
    {
        $test = $this->argument('test');

        if (is_string($test) && $this->shouldRunUrlTest($test)) {
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
    protected function executeTests(): mixed
    {
        $this->info('Starting VoltTest performance tests...');

        $streamOutput = $this->option('stream');
        if (! is_bool($streamOutput)) {
            $this->error('Invalid value for --stream option. It should be true or false.');

            return null;
        }

        if ($streamOutput) {
            $this->info('Streaming output to console...');
        }

        return $this->testRunner->run($streamOutput);
    }

    /**
     * Handle test results.
     */
    protected function handleResults(TestResult|CloudRun|null $result): void
    {
        if ($result instanceof CloudRun) {
            return;
        }

        if (! $result instanceof TestResult) {
            return;
        }

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
        $options = [
            'method' => $this->option('method') ?? 'GET',
            'scenario_name' => $this->option('scenario-name'),
            'body' => $this->option('body') ?? '',
            'content_type' => $this->option('content-type'),
            'expected_status_code' => (int) ($this->option('code-status') ?? 200),
        ];
        if (is_string($this->option('headers'))) {
            $this->validator->validateJsonString($this->option('headers'));
            $options['headers'] = $this->parseHeaders($this->option('headers'));
        }

        return $options;
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
        if ($searchPath && is_string($searchPath)) {
            $this->validator->validatePath($searchPath);
        } else {
            $searchPath = null;
        }

        return $this->testDiscoverer->findTestClasses($searchPath);
    }
}
