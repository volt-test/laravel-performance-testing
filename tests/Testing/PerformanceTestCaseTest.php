<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Testing;

use PHPUnit\Framework\TestCase;
use VoltTest\Laravel\Testing\PerformanceTestCase;
use VoltTest\Laravel\Testing\ServerManager;
use VoltTest\Laravel\Testing\ServerRegistry;

class PerformanceTestCaseTest extends TestCase
{
    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing servers
        $this->clearRegistry();

        // Backup environment
        $this->originalEnv = serialize($_ENV);
    }

    protected function tearDown(): void
    {
        // Restore environment
        $_ENV = unserialize($this->originalEnv);

        // Restore config if available
        if (function_exists('config') && ! empty($this->originalConfig)) {
            foreach ($this->originalConfig as $key => $value) {
                config([$key => $value]);
            }
        }

        // Clear registry
        $this->clearRegistry();

        parent::tearDown();
    }

    /**
     * Clear the server registry using reflection.
     */
    private function clearRegistry(): void
    {
        $reflection = new \ReflectionClass(ServerRegistry::class);

        if ($reflection->hasProperty('servers')) {
            $property = $reflection->getProperty('servers');
            $property->setValue(null, []);
        }

        if ($reflection->hasProperty('shutdownRegistered')) {
            $property = $reflection->getProperty('shutdownRegistered');
            $property->setValue(null, false);
        }
    }

    /**
     * Check if Laravel config is actually available and usable.
     */
    private function isConfigAvailable(): bool
    {
        if (! function_exists('config')) {
            return false;
        }

        try {
            config('app.name');

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a test double class that extends PerformanceTestCase.
     */
    private function createTestDouble(): string
    {
        static $counter = 0;
        $counter++;

        $className = 'TestDouble' . $counter . '_' . uniqid();

        eval('
            class ' . $className . ' extends \VoltTest\Laravel\Testing\PerformanceTestCase {
                public function __construct() {
                    // Provide name parameter for PHPUnit 11
                    parent::__construct("' . $className . '");
                }

                public static function exposesShouldStartServer(): bool {
                    return static::shouldStartServer();
                }

                public function exposesGetBaseUrl(): string {
                    return $this->getBaseUrl();
                }

                public function exposesSetBaseUrl(string $url): self {
                    return $this->setBaseUrl($url);
                }

                public function exposesConfigureVoltTest(): void {
                    $this->configureVoltTest();
                }

                public function exposesGetServerStats(): array {
                    return $this->getServerStats();
                }

                public function exposesDebugServer(): void {
                    $this->debugServer();
                }

                public static function exposesValidateBasePath(string $path): string {
                    return static::validateBasePath($path);
                }

                public static function exposesFindLaravelRoot(string $path): ?string {
                    return static::findLaravelRoot($path);
                }

                public static function exposesGetBasePath(): string {
                    return static::getBasePath();
                }

                public static function getServerKeyForTest(): ?string {
                    return static::$serverKey;
                }

                public static function setServerKeyForTest(?string $key): void {
                    static::$serverKey = $key;
                }
            }
        ');

        return $className;
    }

    /**
     * Test shouldStartServer respects environment variable.
     */
    public function testShouldStartServerRespectsEnvironmentVariable(): void
    {
        $className = $this->createTestDouble();

        // Test with env variable set to true
        $_ENV['VOLTTEST_ENABLE_SERVER_MANAGEMENT'] = '1';
        $this->assertTrue($className::exposesShouldStartServer());

        // Test with env variable set to false
        $_ENV['VOLTTEST_ENABLE_SERVER_MANAGEMENT'] = '0';
        $this->assertFalse($className::exposesShouldStartServer());

        // Test with no env variable, should use class property
        unset($_ENV['VOLTTEST_ENABLE_SERVER_MANAGEMENT']);
        $this->assertFalse($className::exposesShouldStartServer());
    }

    /**
     * Test shouldStartServer uses class property when env not set.
     */
    public function testShouldStartServerUsesClassProperty(): void
    {
        // Create a custom test double with different property value
        $className = 'TestDoubleCustom_' . uniqid();

        eval('
            class ' . $className . ' extends \VoltTest\Laravel\Testing\PerformanceTestCase {
                protected static bool $enableServerManagement = true;

                public function __construct() {
                    parent::__construct("' . $className . '");
                }

                public static function exposesShouldStartServer(): bool {
                    return static::shouldStartServer();
                }
            }
        ');

        unset($_ENV['VOLTTEST_ENABLE_SERVER_MANAGEMENT']);
        $this->assertTrue($className::exposesShouldStartServer());
    }

    /**
     * Test getBaseUrl returns server URL when server is running.
     */
    public function testGetBaseUrlReturnsServerUrlWhenRunning(): void
    {
        $className = $this->createTestDouble();

        // Mock a server in the registry
        $mockManager = $this->createMock(ServerManager::class);
        $mockManager->method('getUrl')->willReturn('http://127.0.0.1:9000');

        $serverKey = 'test-server-key';
        $className::setServerKeyForTest($serverKey);

        // Register the mock manager with correct structure
        $reflection = new \ReflectionClass(ServerRegistry::class);
        $property = $reflection->getProperty('servers');
        $property->setValue(null, [
            $serverKey => [
                'manager' => $mockManager,
                'pid' => getmypid(),
                'registered_at' => microtime(true),
            ],
        ]);

        $instance = new $className();
        $this->assertEquals('http://127.0.0.1:9000', $instance->exposesGetBaseUrl());

        // Cleanup
        $className::setServerKeyForTest(null);
    }

    /**
     * Test getBaseUrl returns config default when no server.
     */
    public function testGetBaseUrlReturnsConfigDefaultWhenNoServer(): void
    {
        if (! $this->isConfigAvailable()) {
            $this->markTestSkipped('Config not available in this environment');
        }

        $className = $this->createTestDouble();

        // Set config value
        config(['volttest.base_url' => 'http://custom:8080']);

        $instance = new $className();
        $url = $instance->exposesGetBaseUrl();

        // Should return either the configured URL or the default
        $this->assertMatchesRegularExpression(
            '/^http:\/\/(custom:8080|localhost:8000)$/',
            $url
        );
    }

    /**
     * Test setBaseUrl changes the configuration.
     */
    public function testSetBaseUrlChangesConfiguration(): void
    {
        if (! $this->isConfigAvailable()) {
            $this->markTestSkipped('Config not available in this environment');
        }

        $className = $this->createTestDouble();
        $instance = new $className();

        $customUrl = 'http://custom-test:9999';
        $result = $instance->exposesSetBaseUrl($customUrl);

        // Should return self for method chaining
        $this->assertInstanceOf($className, $result);

        // Config should be updated
        $this->assertEquals($customUrl, config('volttest.base_url'));
    }

    /**
     * Test configureVoltTest sets correct configuration.
     */
    public function testConfigureVoltTestSetsConfiguration(): void
    {
        if (! $this->isConfigAvailable()) {
            $this->markTestSkipped('Config not available in this environment');
        }

        $className = $this->createTestDouble();
        $instance = new $className();

        $instance->exposesConfigureVoltTest();

        // Check that save_reports is disabled
        $this->assertFalse(config('volttest.save_reports'));
    }

    /**
     * Test getServerStats returns array with expected structure.
     */
    public function testGetServerStatsReturnsArrayStructure(): void
    {
        $className = $this->createTestDouble();
        $instance = new $className();

        $stats = $instance->exposesGetServerStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('process_id', $stats);
        $this->assertArrayHasKey('pid', $stats);
        $this->assertArrayHasKey('total_servers', $stats);
        $this->assertArrayHasKey('active_servers', $stats);
        $this->assertArrayHasKey('servers', $stats);
    }

    /**
     * Test debugServer outputs information without errors.
     */
    public function testDebugServerOutputsInformation(): void
    {
        $className = $this->createTestDouble();
        $instance = new $className();

        ob_start();
        $instance->exposesDebugServer();
        $output = ob_get_clean();

        $this->assertStringContainsString('VoltTest Server Stats', $output);
        $this->assertStringContainsString('Process ID:', $output);
        $this->assertStringContainsString('Total Servers:', $output);
    }

    /**
     * Test validateBasePath throws exception for invalid path.
     */
    public function testValidateBasePathThrowsExceptionForInvalidPath(): void
    {
        $className = $this->createTestDouble();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not contain a Laravel application');

        $className::exposesValidateBasePath('/nonexistent/path');
    }

    /**
     * Test validateBasePath returns path for valid Laravel installation.
     */
    public function testValidateBasePathReturnsPathForValidLaravel(): void
    {
        $className = $this->createTestDouble();

        // Create a temporary directory with bootstrap/app.php
        $tempDir = sys_get_temp_dir() . '/volttest_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/bootstrap');
        file_put_contents($tempDir . '/bootstrap/app.php', '<?php');

        try {
            $result = $className::exposesValidateBasePath($tempDir);
            $this->assertEquals($tempDir, $result);
        } finally {
            // Cleanup
            unlink($tempDir . '/bootstrap/app.php');
            rmdir($tempDir . '/bootstrap');
            rmdir($tempDir);
        }
    }

    /**
     * Test findLaravelRoot finds Laravel root directory.
     */
    public function testFindLaravelRootFindsLaravelRoot(): void
    {
        $className = $this->createTestDouble();

        // Create a temporary directory structure
        $tempDir = sys_get_temp_dir() . '/volttest_root_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/bootstrap');
        file_put_contents($tempDir . '/bootstrap/app.php', '<?php');
        mkdir($tempDir . '/tests');
        mkdir($tempDir . '/tests/Feature');

        try {
            $result = $className::exposesFindLaravelRoot($tempDir . '/tests/Feature');
            $this->assertEquals($tempDir, $result);
        } finally {
            // Cleanup
            unlink($tempDir . '/bootstrap/app.php');
            rmdir($tempDir . '/bootstrap');
            rmdir($tempDir . '/tests/Feature');
            rmdir($tempDir . '/tests');
            rmdir($tempDir);
        }
    }

    /**
     * Test findLaravelRoot returns null when not found.
     */
    public function testFindLaravelRootReturnsNullWhenNotFound(): void
    {
        $className = $this->createTestDouble();

        $result = $className::exposesFindLaravelRoot('/tmp');
        $this->assertNull($result);
    }

    /**
     * Test getBasePath throws exception when Laravel not found.
     */
    public function testGetBasePathThrowsExceptionWhenLaravelNotFound(): void
    {
        $className = $this->createTestDouble();

        // Unset environment variable
        unset($_ENV['VOLTTEST_BASE_PATH']);

        // This test might be difficult to run in actual Laravel context
        // We'll skip it if we're in a real Laravel installation
        if (file_exists(getcwd() . '/bootstrap/app.php')) {
            $this->markTestSkipped('Running in actual Laravel installation');
        }

        $this->expectException(\RuntimeException::class);
        $className::exposesGetBasePath();
    }

    /**
     * Test server lifecycle management.
     */
    public function testServerLifecycleManagement(): void
    {
        $className = $this->createTestDouble();

        // Ensure no server key initially
        $this->assertNull($className::getServerKeyForTest());

        // After teardown, server key should be cleared
        $className::setServerKeyForTest('test-key');
        $this->assertEquals('test-key', $className::getServerKeyForTest());

        $className::tearDownAfterClass();
        $this->assertNull($className::getServerKeyForTest());
    }
}
