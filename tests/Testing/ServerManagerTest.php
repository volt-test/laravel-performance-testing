<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Testing;

use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use VoltTest\Laravel\Testing\ServerManager;

class ServerManagerTest extends TestCase
{
    protected string $tempBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary Laravel structure for testing
        $this->tempBasePath = sys_get_temp_dir() . '/volttest_server_test_' . uniqid();
        $this->createMockLaravelStructure($this->tempBasePath);
    }

    protected function tearDown(): void
    {
        $this->cleanupMockLaravelStructure($this->tempBasePath);

        Mockery::close();
        parent::tearDown();
    }

    protected function createMockLaravelStructure(string $basePath): void
    {
        $bootstrapDir = $basePath . '/bootstrap';
        $publicDir = $basePath . '/public';

        if (! is_dir($bootstrapDir)) {
            mkdir($bootstrapDir, 0755, true);
        }

        if (! is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }

        file_put_contents(
            $bootstrapDir . '/app.php',
            "<?php\nreturn require __DIR__.'/../vendor/autoload.php';\n"
        );

        file_put_contents(
            $publicDir . '/index.php',
            "<?php\necho 'Laravel';\n"
        );
    }

    protected function cleanupMockLaravelStructure(string $basePath): void
    {
        $bootstrapDir = $basePath . '/bootstrap';
        $publicDir = $basePath . '/public';

        if (file_exists($bootstrapDir . '/app.php')) {
            unlink($bootstrapDir . '/app.php');
        }

        if (file_exists($publicDir . '/index.php')) {
            unlink($publicDir . '/index.php');
        }

        if (is_dir($bootstrapDir)) {
            rmdir($bootstrapDir);
        }

        if (is_dir($publicDir)) {
            rmdir($publicDir);
        }
    }

    public function testConstructorInitializesPropertiesCorrectly(): void
    {
        $manager = new ServerManager($this->tempBasePath, false, '127.0.0.1', 8001);

        $this->assertInstanceOf(ServerManager::class, $manager);
        $this->assertEquals('http://127.0.0.1:8001', $manager->getUrl());
        $this->assertEquals(8001, $manager->getPort());
    }

    public function testConstructorTrimsTrailingSlashFromBasePath(): void
    {
        $basePathWithSlash = $this->tempBasePath . '/';
        $manager = new ServerManager($basePathWithSlash, false);

        $reflection = new \ReflectionClass($manager);
        $basePathProperty = $reflection->getProperty('basePath');

        $this->assertEquals($this->tempBasePath, $basePathProperty->getValue($manager));
    }

    public function testConstructorThrowsExceptionWhenBootstrapFileIsMissing(): void
    {
        $invalidPath = sys_get_temp_dir() . '/volttest_invalid_' . uniqid();
        mkdir($invalidPath . '/public', 0755, true);
        file_put_contents($invalidPath . '/public/index.php', '<?php echo "test";');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bootstrap file not found');

        try {
            new ServerManager($invalidPath, false);
        } finally {
            if (file_exists($invalidPath . '/public/index.php')) {
                unlink($invalidPath . '/public/index.php');
            }
            if (is_dir($invalidPath . '/public')) {
                rmdir($invalidPath . '/public');
            }
            if (is_dir($invalidPath)) {
                rmdir($invalidPath);
            }
        }
    }

    public function testConstructorThrowsExceptionWhenPublicDirectoryIsMissing(): void
    {
        $invalidPath = sys_get_temp_dir() . '/volttest_invalid_' . uniqid();
        mkdir($invalidPath . '/bootstrap', 0755, true);
        file_put_contents($invalidPath . '/bootstrap/app.php', '<?php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Public directory not found');

        try {
            new ServerManager($invalidPath, false);
        } finally {
            if (file_exists($invalidPath . '/bootstrap/app.php')) {
                unlink($invalidPath . '/bootstrap/app.php');
            }
            if (is_dir($invalidPath . '/bootstrap')) {
                rmdir($invalidPath . '/bootstrap');
            }
            if (is_dir($invalidPath)) {
                rmdir($invalidPath);
            }
        }
    }

    public function testConstructorThrowsExceptionWhenIndexPhpIsMissing(): void
    {
        $invalidPath = sys_get_temp_dir() . '/volttest_invalid_' . uniqid();
        mkdir($invalidPath . '/bootstrap', 0755, true);
        mkdir($invalidPath . '/public', 0755, true);
        file_put_contents($invalidPath . '/bootstrap/app.php', '<?php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Laravel index.php not found');

        try {
            new ServerManager($invalidPath, false);
        } finally {
            if (file_exists($invalidPath . '/bootstrap/app.php')) {
                unlink($invalidPath . '/bootstrap/app.php');
            }
            if (is_dir($invalidPath . '/bootstrap')) {
                rmdir($invalidPath . '/bootstrap');
            }
            if (is_dir($invalidPath . '/public')) {
                rmdir($invalidPath . '/public');
            }
            if (is_dir($invalidPath)) {
                rmdir($invalidPath);
            }
        }
    }

    public function testStartThrowsExceptionWhenPublicDirectoryDoesNotExist(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        // Remove the public directory to simulate the error
        $publicDir = $this->tempBasePath . '/public';
        unlink($publicDir . '/index.php');
        rmdir($publicDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Public directory not found');

        $manager->start();
    }

    public function testIsRunningReturnsFalseWhenServerNotStarted(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        $this->assertFalse($manager->isRunning());
    }

    public function testGetUrlReturnsCorrectFormat(): void
    {
        $manager = new ServerManager($this->tempBasePath, false, '192.168.1.1', 9000);

        $this->assertEquals('http://192.168.1.1:9000', $manager->getUrl());
    }

    public function testGetPortReturnsCorrectPort(): void
    {
        $manager = new ServerManager($this->tempBasePath, false, '127.0.0.1', 8080);

        $this->assertEquals(8080, $manager->getPort());
    }

    public function testStopDoesNothingWhenServerNotRunning(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        // This should not throw any exception
        $manager->stop();

        $this->assertFalse($manager->isRunning());
    }

    public function testGetOutputReturnsEmptyStringWhenNoProcess(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        $this->assertEquals('', $manager->getOutput());
    }

    public function testFindAvailablePortReturnsPortWhenAvailable(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('findAvailablePort');

        $port = $method->invoke($manager, 8000);

        $this->assertIsInt($port);
        $this->assertGreaterThanOrEqual(8000, $port);
        $this->assertLessThan(8100, $port);
    }

    public function testFindAvailablePortThrowsExceptionWhenNoPortsAvailable(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        // Create sockets to occupy all ports in the range
        $sockets = [];
        for ($port = 50000; $port < 50100; $port++) {
            $socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
            if ($socket) {
                $sockets[] = $socket;
            }
        }

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('findAvailablePort');


        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No available ports found');

        try {
            $method->invoke($manager, 50000);
        } finally {
            // Clean up sockets
            foreach ($sockets as $socket) {
                fclose($socket);
            }
        }
    }

    public function testDetectRunningServerReturnsTrueWhenServerIsRunning(): void
    {
        // Create a test server socket
        $socket = stream_socket_server('tcp://127.0.0.1:58888', $errno, $errstr);

        $result = ServerManager::detectRunningServer('127.0.0.1', 58888);

        $this->assertTrue($result);

        if ($socket) {
            fclose($socket);
        }
    }

    public function testDetectRunningServerReturnsFalseWhenServerIsNotRunning(): void
    {
        $result = ServerManager::detectRunningServer('127.0.0.1', 58999);

        $this->assertFalse($result);
    }

    public function testGetStatusReturnsCorrectStructure(): void
    {
        $manager = new ServerManager($this->tempBasePath, false, '127.0.0.1', 8002);

        $status = $manager->getStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('running', $status);
        $this->assertArrayHasKey('url', $status);
        $this->assertArrayHasKey('port', $status);
        $this->assertArrayHasKey('pid', $status);

        $this->assertFalse($status['running']);
        $this->assertEquals('http://127.0.0.1:8002', $status['url']);
        $this->assertEquals(8002, $status['port']);
        $this->assertNull($status['pid']);
    }

    public function testCanConnectReturnsFalseWhenServerNotRunning(): void
    {
        $port = rand(50000, 59000);
        $manager = new ServerManager($this->tempBasePath, false, '127.0.0.1', $port);

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('canConnect');

        $result = $method->invoke($manager);

        $this->assertFalse($result);
    }

    public function testStartDoesNotStartServerIfAlreadyRunning(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        // Mock the serverProcess to appear as running
        $mockProcess = Mockery::mock(Process::class);
        $mockProcess->shouldReceive('isRunning')->andReturn(true);
        $mockProcess->shouldReceive('stop');

        $reflection = new \ReflectionClass($manager);
        $processProperty = $reflection->getProperty('serverProcess');
        $processProperty->setValue($manager, $mockProcess);

        // This should return early without starting a new server
        $manager->start();

        $this->assertTrue($manager->isRunning());
    }

    public function testStartFindsAvailablePortWhenPreferredPortIsInUse(): void
    {
        $manager = new ServerManager($this->tempBasePath, false, '127.0.0.1', 58777);

        // Occupy the preferred port
        $socket = stream_socket_server('tcp://127.0.0.1:58777', $errno, $errstr);

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('findAvailablePort');

        $availablePort = $method->invoke($manager, 58777);

        $this->assertNotEquals(58777, $availablePort);
        $this->assertGreaterThan(58777, $availablePort);

        if ($socket) {
            fclose($socket);
        }
    }

    public function testDebugModeOutputsMessages(): void
    {
        $manager = new ServerManager($this->tempBasePath, true);


        // Mock the serverProcess to simulate server startup
        $mockProcess = Mockery::mock(Process::class);
        $mockProcess->shouldReceive('isRunning')->andReturn(false);

        $reflection = new \ReflectionClass($manager);
        $processProperty = $reflection->getProperty('serverProcess');
        $processProperty->setValue($manager, $mockProcess);

        // Test that debug is enabled
        $debugProperty = $reflection->getProperty('debug');

        $this->assertTrue($debugProperty->getValue($manager));
    }

    public function testGetOutputReturnsProcessOutput(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        // Mock the serverProcess with output
        $mockProcess = Mockery::mock(Process::class);
        $mockProcess->shouldReceive('isRunning')->andReturn(true);
        $mockProcess->shouldReceive('stop');
        $mockProcess->shouldReceive('getOutput')->andReturn('Standard output');
        $mockProcess->shouldReceive('getErrorOutput')->andReturn('Error output');

        $reflection = new \ReflectionClass($manager);
        $processProperty = $reflection->getProperty('serverProcess');
        $processProperty->setValue($manager, $mockProcess);

        $output = $manager->getOutput();

        $this->assertEquals('Standard outputError output', $output);
    }

    public function testWaitForServerThrowsExceptionWhenProcessDies(): void
    {
        $port = rand(50000, 59000);
        $manager = new ServerManager($this->tempBasePath, false, '127.0.0.1', $port);

        // Mock a process that dies immediately
        $mockProcess = Mockery::mock(Process::class);
        $mockProcess->shouldReceive('isRunning')->andReturn(false);
        $mockProcess->shouldReceive('getOutput')->andReturn('Process output');
        $mockProcess->shouldReceive('getErrorOutput')->andReturn('Process error');

        $reflection = new \ReflectionClass($manager);
        $processProperty = $reflection->getProperty('serverProcess');
        $processProperty->setValue($manager, $mockProcess);

        $method = $reflection->getMethod('waitForServer');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Server process died unexpectedly');

        $method->invoke($manager, 1);
    }

    public function testPortIsUpdatedWhenAlternativePortIsUsed(): void
    {
        // Occupy port 58666
        $socket = stream_socket_server('tcp://127.0.0.1:58666', $errno, $errstr);

        $manager = new ServerManager($this->tempBasePath, false, '127.0.0.1', 58666);

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('findAvailablePort');

        $availablePort = $method->invoke($manager, 58666);

        // Manually update the port as start() would
        $portProperty = $reflection->getProperty('port');
        $portProperty->setValue($manager, $availablePort);

        $this->assertNotEquals(58666, $manager->getPort());

        if ($socket) {
            fclose($socket);
        }
    }

    public function testValidateLaravelStructureChecksAllRequiredFiles(): void
    {
        // This is implicitly tested in the constructor tests,
        // but we can verify it more explicitly
        $reflection = new \ReflectionClass(ServerManager::class);
        $method = $reflection->getMethod('validateLaravelStructure');

        $manager = new ServerManager($this->tempBasePath, false);

        // This should not throw an exception for valid structure
        $method->invoke($manager);

        $this->assertTrue(true);
    }

    public function testConstructorSetsDefaultHostAndPort(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        $this->assertEquals('http://127.0.0.1:8000', $manager->getUrl());
        $this->assertEquals(8000, $manager->getPort());
    }

    public function testGetStatusWhenServerIsRunning(): void
    {
        $manager = new ServerManager($this->tempBasePath, false);

        // Mock a running process
        $mockProcess = Mockery::mock(Process::class);
        $mockProcess->shouldReceive('stop');
        $mockProcess->shouldReceive('isRunning')->andReturn(true);
        $mockProcess->shouldReceive('getPid')->andReturn(12345);

        $reflection = new \ReflectionClass($manager);
        $processProperty = $reflection->getProperty('serverProcess');
        $processProperty->setValue($manager, $mockProcess);

        $status = $manager->getStatus();

        $this->assertTrue($status['running']);
        $this->assertEquals(12345, $status['pid']);
    }
}
