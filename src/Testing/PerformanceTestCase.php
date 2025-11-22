<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Testing;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use VoltTest\Laravel\Contracts\VoltTestCase as VoltTestCaseInterface;
use VoltTest\Laravel\Facades\VoltTest;
use VoltTest\Laravel\Testing\Assertions\VoltTestAssertions;

/**
 * Base test case for VoltTest performance testing with PHPUnit.
 *
 * Provides automatic server management, performance assertions,
 * and helper methods for running load tests within PHPUnit test suites.
 *
 * @package VoltTest\Laravel\Testing
 */
abstract class PerformanceTestCase extends BaseTestCase
{
    use VoltTestAssertions;

    /**
     * Preferred port for the test server.
     * If null, an available port will be automatically assigned.
     */
    protected static ?int $preferredPort = 8000;

    /**
     * Whether server management is enabled.
     */
    protected static bool $enableServerManagement = false;

    /**
     * Whether debug mode is enabled for server management.
     */
    protected static bool $enableDebugForServerManagement = false;

    /**
     * The server key for this test class.
     */
    protected static ?string $serverKey = null;

    /**
     * The last test result for reporting.
     */
    protected static mixed $lastTestResult = null;

    /**
     * Get the last test result.
     */
    public static function getLastTestResult(): mixed
    {
        return static::$lastTestResult;
    }

    /**
     * Clear the last test result.
     */
    public static function clearLastTestResult(): void
    {
        static::$lastTestResult = null;
    }

    /**
     * Setup before first test in the class.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Register cleanup handler
        ServerRegistry::registerShutdownHandler();

        if (static::shouldStartServer()) {
            static::startServer();
        }
    }

    /**
     * Teardown after all tests in the class.
     */
    public static function tearDownAfterClass(): void
    {
        if (static::$serverKey) {
            ServerRegistry::stop(static::$serverKey);
            static::$serverKey = null;
        }

        parent::tearDownAfterClass();
    }

    /**
     * Setup before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configureVoltTest();
    }

    /**
     * Determine if server should be started.
     */
    protected static function shouldStartServer(): bool
    {
        return (bool) env('VOLTTEST_ENABLE_SERVER_MANAGEMENT', static::$enableServerManagement);
    }

    /**
     * Start the test server using the registry.
     */
    protected static function startServer(): void
    {
        try {
            $basePath = static::getBasePath();
            $host = '127.0.0.1';
            // Get or create a server for this test class
            $manager = ServerRegistry::getOrCreate(
                static::class,
                $basePath,
                (bool) env('VOLTTEST_DEBUG_FOR_SERVER_MANAGEMENT', static::$enableDebugForServerManagement),
                $host,
                static::$preferredPort
            );

            // Start the server if not already running
            if (! $manager->isRunning()) {
                $manager->start();
            }

            // Store the server key
            static::$serverKey = ServerRegistry::generateKey(static::class, $host);

            if (env('VOLTTEST_DEBUG', false)) {
                echo sprintf(
                    "[VoltTest] Server started for %s at %s (PID: %d)\n",
                    static::class,
                    $manager->getUrl(),
                    getmypid()
                );
            }
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to start test server for " . static::class . ": " . $e->getMessage()
            );
        }
    }

    /**
     * Get the base URL for the test server.
     */
    protected function getBaseUrl(): string
    {
        if (static::$serverKey) {
            $manager = ServerRegistry::get(static::$serverKey);
            if ($manager) {
                return $manager->getUrl();
            }
        }

        return config('volttest.base_url', 'http://localhost:8000');
    }

    /**
     * Set custom base URL for testing.
     */
    protected function setBaseUrl(string $url): self
    {
        config(['volttest.base_url' => $url]);

        return $this;
    }

    /**
     * Configure VoltTest with sensible defaults.
     */
    protected function configureVoltTest(): void
    {
        $baseUrl = $this->getBaseUrl();

        config([
            'volttest.base_url' => $baseUrl,
            'volttest.save_reports' => false,
            'volttest.http_debug' => env('VOLTTEST_HTTP_DEBUG', false),
        ]);
    }

    /**
     * Get the base path for the Laravel application.
     */
    protected static function getBasePath(): string
    {
        if ($basePath = env('VOLTTEST_BASE_PATH')) {
            return static::validateBasePath($basePath);
        }

        // 2. Try to find from test file location
        $reflection = new \ReflectionClass(static::class);
        if (! $reflection->getFileName()) {
            throw new \RuntimeException(
                'Could not determine file location of the test class: ' . static::class
            );
        }
        $testPath = dirname($reflection->getFileName());

        if ($basePath = static::findLaravelRoot($testPath)) {
            return $basePath;
        }

        // 3. Common locations as fallback
        $candidates = [
            getcwd(),
            dirname(__DIR__, 4), // vendor location
            dirname(__DIR__, 3), // workbench
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate . '/bootstrap/app.php')) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'Could not find Laravel base path. Set VOLTTEST_BASE_PATH environment variable.'
        );
    }

    /**
     * Find Laravel root by walking up the directory tree.
     */
    protected static function findLaravelRoot(string $startPath): ?string
    {
        $currentPath = $startPath;
        $maxDepth = 10;
        $depth = 0;

        while ($currentPath !== dirname($currentPath) && $depth < $maxDepth) {
            if (file_exists($currentPath . '/bootstrap/app.php')) {
                return $currentPath;
            }
            $currentPath = dirname($currentPath);
            $depth++;
        }

        return null;
    }

    /**
     * Validate that a path contains a Laravel application.
     */
    protected static function validateBasePath(string $basePath): string
    {
        if (! file_exists($basePath . '/bootstrap/app.php')) {
            throw new \RuntimeException(
                "Path '{$basePath}' does not contain a Laravel application"
            );
        }

        return $basePath;
    }

    /**
     * Creates the Laravel application.
     */
    public function createApplication()
    {
        $app = require static::getBasePath() . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Run a VoltTest performance test.
     */
    protected function runVoltTest(
        VoltTestCaseInterface $testClass,
        array $options = []
    ): mixed {
        try {
            // Apply default options
            $options = array_merge([
                'virtual_users' => 5,
                'stream' => false,
            ], $options);

            // Configure test parameters
            $this->applyVoltTestOptions($options);

            // Add the test class
            VoltTest::addTestFromClass($testClass);

            // Run the test
            $result = VoltTest::run($options['stream'] ?? false);

            // Store result for listener
            static::$lastTestResult = $result;

            return $result;
        } catch (\Exception $e) {
            $this->fail("VoltTest execution failed: " . $e->getMessage());
        }
    }

    /**
     * Apply VoltTest configuration options.
     */
    protected function applyVoltTestOptions(array $options): void
    {
        $voltTest = VoltTest::getVoltTest();

        if (isset($options['virtual_users'])) {
            $voltTest->setVirtualUsers($options['virtual_users']);
        }

        if (isset($options['duration'])) {
            $voltTest->setDuration($options['duration']);
        }

        if (isset($options['http_debug'])) {
            $voltTest->setHttpDebug($options['http_debug']);
        }

        if (isset($options['ramp_up'])) {
            $voltTest->setRampUp($options['ramp_up']);
        }
    }

    /**
     * Quick helper to run a simple URL load test.
     */
    protected function loadTestUrl(string $url, array $options = []): mixed
    {
        $testClass = new class ($url) implements VoltTestCaseInterface {
            public function __construct(private string $url)
            {
            }

            public function define(\VoltTest\Laravel\VoltTestManager $manager): void
            {
                $scenario = $manager->scenario('URL Load Test');
                $scenario->step('Load URL')
                    ->get($this->url)
                    ->expectStatus(200);
            }
        };

        return $this->runVoltTest($testClass, $options);
    }

    /**
     * Helper to quickly test an API endpoint.
     */
    protected function loadTestApi(
        string $endpoint,
        string $method = 'GET',
        array $data = [],
        array $options = []
    ): mixed {
        $testClass = new class ($endpoint, $method, $data) implements VoltTestCaseInterface {
            public function __construct(
                private string $endpoint,
                private string $method,
                private array $data
            ) {
            }

            public function define(\VoltTest\Laravel\VoltTestManager $manager): void
            {
                $scenario = $manager->scenario('API Load Test');
                $step = $scenario->step('API Request');

                match(strtoupper($this->method)) {
                    'POST' => $step->post($this->endpoint, $this->data),
                    'PUT' => $step->put($this->endpoint, $this->data),
                    'PATCH' => $step->patch($this->endpoint, $this->data),
                    'DELETE' => $step->delete($this->endpoint),
                    default => $step->get($this->endpoint),
                };

                $step->header('Accept', 'application/json')
                    ->expectStatus(200);
            }
        };

        return $this->runVoltTest($testClass, $options);
    }

    /**
     * Get server statistics (useful for debugging parallel tests).
     */
    protected function getServerStats(): array
    {
        return ServerRegistry::getStats();
    }

    /**
     * Debug helper to print server information.
     */
    protected function debugServer(): void
    {
        $stats = $this->getServerStats();

        echo "\n=== VoltTest Server Stats ===\n";
        echo "Process ID: {$stats['process_id']}\n";
        echo "PID: {$stats['pid']}\n";
        echo "Total Servers: {$stats['total_servers']}\n";
        echo "Active Servers: {$stats['active_servers']}\n";

        foreach ($stats['servers'] as $key => $server) {
            echo "\nServer: {$key}\n";
            echo "  URL: {$server['url']}\n";
            echo "  Port: {$server['port']}\n";
            echo "  Running: " . ($server['running'] ? 'Yes' : 'No') . "\n";
            echo "  Uptime: " . round($server['uptime'], 2) . "s\n";
        }

        echo "============================\n\n";
    }
}
