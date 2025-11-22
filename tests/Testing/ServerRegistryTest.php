<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Testing;

use Mockery;
use PHPUnit\Framework\TestCase;
use VoltTest\Laravel\Testing\ServerManager;
use VoltTest\Laravel\Testing\ServerRegistry;

class ServerRegistryTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        ServerRegistry::stopAll();

        $this->resetRegistryState();
    }

    protected function tearDown(): void
    {
        ServerRegistry::stopAll();
        ServerRegistry::cleanupOrphaned();

        Mockery::close();
        parent::tearDown();
    }

    protected function resetRegistryState(): void
    {
        $reflection = new \ReflectionClass(ServerRegistry::class);

        $serversProperty = $reflection->getProperty('servers');
        $serversProperty->setValue(null, []);

        $processIdsProperty = $reflection->getProperty('processId');
        $processIdsProperty->setValue(null, null);
    }

    protected function getServersArray(): array
    {
        $reflection = new \ReflectionClass(ServerRegistry::class);
        $serversProperty = $reflection->getProperty('servers');

        return $serversProperty->getValue();
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

    public function testGeneratesUniqueProcessId(): void
    {
        $reflection = new \ReflectionClass(ServerRegistry::class);
        $method = $reflection->getMethod('getProcessId');
        $currentProcessId = getmypid();

        $processId1 = $method->invoke(null);
        $processId2 = $method->invoke(null);

        $this->assertIsString($processId1);
        $this->assertNotEmpty($processId1);

        $this->assertIsString($processId2);
        $this->assertNotEmpty($processId2);

        $this->assertEquals($processId1, $processId2, 'Process ID should be consistent across calls.');
        $this->assertStringContainsString((string) $currentProcessId, $processId1, 'Process ID should contain hyphens.');
    }

    public function testGeneratesUniqueKeysForDifferentTestClasses(): void
    {
        $key1 = ServerRegistry::generateKey('TestClassA');
        $key2 = ServerRegistry::generateKey('TestClassB');

        $this->assertNotEquals($key1, $key2, 'Keys for different test classes should be unique.');
        $this->assertIsString($key1);
        $this->assertIsString($key2);
        $this->assertEquals(32, strlen($key1), 'Key should be an MD5 hash of length 32.');
        $this->assertEquals(32, strlen($key2), 'Key should be an MD5 hash of length 32.');
    }

    public function testGeneratesUniqueKeysForDifferentHosts(): void
    {
        $key1 = ServerRegistry::generateKey('TestClassA', '127.0.0.1');
        $key2 = ServerRegistry::generateKey('TestClassA', '192.168.1.1');

        $this->assertNotEquals($key1, $key2, 'Keys for different hosts should be unique.');
    }

    public function testGeneratesSameKeyForSameParameters(): void
    {
        $key1 = ServerRegistry::generateKey('TestClassA', '192.168.1.1');
        $key2 = ServerRegistry::generateKey('TestClassA', '192.168.1.1');

        $this->assertEquals($key1, $key2, 'Keys for same parameters should be identical.');
    }

    public function testRegistersServerManagerSuccessfully(): void
    {
        $mockServerManager = Mockery::mock(ServerManager::class);
        $mockServerManager->shouldReceive('stop')->once()->andReturnNull();
        $key = 'test_key_' . uniqid();

        ServerRegistry::register($key, $mockServerManager);

        $servers = $this->getServersArray();


        $this->assertArrayHasKey($key, $servers, 'Server registry should contain the registered key.');
        $this->assertArrayHasKey('manager', $servers[$key], 'Registered server should have a manager.');
        $this->assertArrayHasKey('pid', $servers[$key], 'Registered server should have a pid.');
        $this->assertArrayHasKey('registered_at', $servers[$key], 'Registered server should have a registered_at timestamp.');
        $this->assertSame($mockServerManager, $servers[$key]['manager'], 'Registered manager should match the mock server manager.');
        $this->assertEquals(getmypid(), $servers[$key]['pid'], 'Registered pid should match the current process id.');
        $this->assertIsFloat($servers[$key]['registered_at'], 'registered_at should be a float timestamp.');
    }

    public function testReturnsNullForNonExistsKey(): void
    {
        $result = ServerRegistry::get('non_existent_key');

        $this->assertNull($result, 'Getting a non-existent key should return null.');
    }

    public function testReturnsFalseForNonExistsKeyWithHas(): void
    {
        $result = ServerRegistry::has('non_existent_key');
        $this->assertFalse($result, 'Has method should return false for non-existent key.');
    }

    public function testCreateNewServerWithGetOrCreate(): void
    {
        $testClass = 'App\\Tests\\TestClass';
        $basePath = sys_get_temp_dir();

        $this->createMockLaravelStructure($basePath);

        $manager = ServerRegistry::getOrCreate($testClass, $basePath, true, '127.0.0.1', 8000);
        $this->assertInstanceOf(ServerManager::class, $manager, 'getOrCreate should return a ServerManager instance.');

        $key = ServerRegistry::generateKey($testClass);
        $this->assertTrue(ServerRegistry::has($key), 'ServerRegistry should have the newly created server.');

        $this->cleanupMockLaravelStructure($basePath);
    }

    public function testReusesExistingServerWithGetOrCreate(): void
    {
        $testClass = 'App\\Tests\\TestClass';
        $basePath = sys_get_temp_dir();
        $this->createMockLaravelStructure($basePath);

        $manager1 = ServerRegistry::getOrCreate($testClass, $basePath, true);
        $manager2 = ServerRegistry::getOrCreate('App\\Tests\\TestClass', $basePath, true);

        $this->assertSame($manager1, $manager2, 'getOrCreate should return the same ServerManager instance for the same test class.');

        $this->cleanupMockLaravelStructure($basePath);
    }

    public function testStopsSpecificServer(): void
    {
        $mockServerManager = Mockery::mock(ServerManager::class);
        $mockServerManager->shouldReceive('stop')->once();

        $key = 'test_key_' . uniqid();
        ServerRegistry::register($key, $mockServerManager);

        $this->assertTrue(ServerRegistry::has($key));

        ServerRegistry::stop($key);

        $this->assertFalse(ServerRegistry::has($key));
    }

    public function testDoesNothingWhenStoppingNonExistentServer(): void
    {
        $key = 'non_existent_key_' . uniqid();

        ServerRegistry::stop($key);

        $this->assertFalse(ServerRegistry::has($key), 'ServerRegistry should not have the non-existent key after stop attempt.');
    }

    public function testStopsAllServersForCurrentProcess(): void
    {
        $mockServerManager1 = Mockery::mock(ServerManager::class);
        $mockServerManager1->shouldReceive('stop')->once();

        $mockServerManager2 = Mockery::mock(ServerManager::class);
        $mockServerManager2->shouldReceive('stop')->once();

        $key1 = 'test_key_1_' . uniqid();
        $key2 = 'test_key_2_' . uniqid();

        ServerRegistry::register($key1, $mockServerManager1);
        ServerRegistry::register($key2, $mockServerManager2);

        $this->assertTrue(ServerRegistry::has($key1));
        $this->assertTrue(ServerRegistry::has($key2));

        ServerRegistry::stopAll();

        $this->assertFalse(ServerRegistry::has($key1), 'ServerRegistry should not have key1 after stopAll.');
        $this->assertFalse(ServerRegistry::has($key2), 'ServerRegistry should not have key2 after stopAll.');
    }

    public function testFindsAvailablePortInWorkerRange(): void
    {
        $reflection = new \ReflectionClass(ServerRegistry::class);
        $method = $reflection->getMethod('findAvailablePortForWorker');

        $port = $method->invoke(null, 8000);

        $this->assertIsInt($port, 'findAvailablePortForWorker should return an integer port.');
        $this->assertGreaterThanOrEqual(8000, $port, 'Port should be greater than or equal to 8000.');
        $this->assertLessThan(9000, $port, 'Port should be less than 8100.');
    }

    public function testDetectsPortInUse(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:59999', $errno, $errstr);
        $reflection = new \ReflectionClass(ServerRegistry::class);
        $method = $reflection->getMethod('isPortInUse');

        $result = $method->invoke(null, 59999, '127.0.0.1'); // Common port likely in use

        $this->assertIsBool($result, 'isPortInUse should return a boolean.');
        $this->AssertTrue($result, 'Port 59999 should be detected as in use.');

        if ($socket) {
            fclose($socket);
        }
    }

    public function testReturnsComprehensiveInfo(): void
    {
        $mockServerManager = Mockery::mock(ServerManager::class);
        $mockServerManager->shouldReceive('isRunning')->andReturn(true);
        $mockServerManager->shouldReceive('getUrl')->andReturn('http://127.0.0.1:8000');
        $mockServerManager->shouldReceive('getPort')->andReturn(8000);
        $mockServerManager->shouldReceive('stop')->once();

        $key = 'test_key_' . uniqid();
        ServerRegistry::register($key, $mockServerManager);

        $stats = ServerRegistry::getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('process_id', $stats);
        $this->assertArrayHasKey('pid', $stats);
        $this->assertArrayHasKey('total_servers', $stats);
        $this->assertArrayHasKey('active_servers', $stats);
        $this->assertArrayHasKey('servers', $stats);

        $this->assertEquals(getmypid(), $stats['pid']);
        $this->assertEquals(1, $stats['total_servers']);
        $this->assertEquals(1, $stats['active_servers']);
        $this->assertArrayHasKey($key, $stats['servers']);
    }

    public function testsIncludesServerDeatailsInStats(): void
    {
        $mockServerManager = Mockery::mock(ServerManager::class);
        $mockServerManager->shouldReceive('isRunning')->andReturn(true);
        $mockServerManager->shouldReceive('getUrl')->andReturn('http://127.0.0.1:8000');
        $mockServerManager->shouldReceive('getPort')->andReturn(8000);
        $mockServerManager->shouldReceive('stop')->once();

        $key = 'test_key_' . uniqid();
        ServerRegistry::register($key, $mockServerManager);

        usleep(10000); // 10ms

        $stats = ServerRegistry::getStats();
        $serverStats = $stats['servers'][$key];

        $this->assertArrayHasKey('running', $serverStats);
        $this->assertArrayHasKey('url', $serverStats);
        $this->assertArrayHasKey('port', $serverStats);
        $this->assertArrayHasKey('uptime', $serverStats);

        $this->assertTrue($serverStats['running']);
        $this->assertEquals('http://127.0.0.1:8000', $serverStats['url']);
        $this->assertEquals(8000, $serverStats['port']);
        $this->assertGreaterThan(0, $serverStats['uptime']);

    }

    public function testCountsOnlyCurrentServersInStats(): void
    {
        $runningManager = Mockery::mock(ServerManager::class);
        $runningManager->shouldReceive('isRunning')->andReturn(true);
        $runningManager->shouldReceive('getUrl')->andReturn('http://127.0.0.1:8000');
        $runningManager->shouldReceive('getPort')->andReturn(8000);
        $runningManager->shouldReceive('stop')->once();

        $stoppedManager = Mockery::mock(ServerManager::class);
        $stoppedManager->shouldReceive('isRunning')->andReturn(false);
        $stoppedManager->shouldReceive('getUrl')->andReturn('http://127.0.0.1:8001');
        $stoppedManager->shouldReceive('getPort')->andReturn(8001);
        $stoppedManager->shouldReceive('stop')->once();

        $key1 = 'running_key_' . uniqid();
        $key2 = 'stopped_key_' . uniqid();

        ServerRegistry::register($key1, $runningManager);
        ServerRegistry::register($key2, $stoppedManager);

        $stats = ServerRegistry::getStats();

        $this->assertEquals(2, $stats['total_servers']);
        $this->assertEquals(1, $stats['active_servers']);
    }

    public function testCleansUpOrphanedServers(): void
    {
        $mockServerManager1 = Mockery::mock(ServerManager::class);
        $mockServerManager1->shouldReceive('isRunning')->andReturn(false);

        $key1 = 'orphaned_key_' . uniqid();

        $reflection = new \ReflectionClass(ServerRegistry::class);
        $serversProperty = $reflection->getProperty('servers');
        $servers = $serversProperty->getValue();

        $servers[$key1] = [
            'manager' => $mockServerManager1,
            'pid' => getmypid() + 1, // Different PID to simulate orphan
            'registered_at' => microtime(true) - 1000,
        ];
        $serversProperty->setValue(null, $servers);

        $this->assertArrayHasKey($key1, $this->getServersArray());
        ServerRegistry::cleanupOrphaned();
        $this->assertArrayNotHasKey($key1, $this->getServersArray());
    }

    public function testKeepsRunningServersDuringCleanup(): void
    {
        $mockServerManager = Mockery::mock(ServerManager::class);
        $mockServerManager->shouldReceive('isRunning')->andReturn(true);
        $mockServerManager->shouldReceive('stop')->once();

        $key = 'active_key_' . uniqid();

        ServerRegistry::register($key, $mockServerManager);

        $this->assertArrayHasKey($key, $this->getServersArray());
        ServerRegistry::cleanupOrphaned();
        $this->assertArrayHasKey($key, $this->getServersArray(), 'Running servers should not be removed during cleanup.');
    }

    public function testRegistersShutdownHandlerOnlyOnce(): void
    {
        ServerRegistry::registerShutdownHandler();

        ServerRegistry::registerShutdownHandler();

        $this->assertTrue(true, 'No errors should occur when registering shutdown handler multiple times.');
    }

    public function testHandleConcurrentRegistrations(): void
    {
        $keys = [];
        $managers = [];
        for ($i = 0; $i < 10; $i++) {
            $mockServerManager = Mockery::mock(ServerManager::class);
            $mockServerManager->shouldReceive('stop')->once();
            $key = 'concurrent_key_' . uniqid();
            $keys[] = $key;
            $managers[] = $mockServerManager;
            ServerRegistry::register($key, $mockServerManager);
        }

        foreach ($keys as $index => $key) {
            $this->assertTrue(ServerRegistry::has($key), "ServerRegistry should have the key: {$key}");
            $this->assertSame($managers[$index], ServerRegistry::get($key), "Registered manager should match for key: {$key}");
        }
    }

    public function testPreventsAccessToServersFromDifferentProcesses(): void
    {
        $mockServerManager = Mockery::mock(ServerManager::class);
        $key = 'test_key_' . uniqid();

        ServerRegistry::register($key, $mockServerManager);
        $this->assertTrue(ServerRegistry::has($key), 'ServerRegistry should have the registered key.');

        // Simulate different process by changing the process ID via reflection
        $reflection = new \ReflectionClass(ServerRegistry::class);
        $serversProperty = $reflection->getProperty('servers');

        $servers = $serversProperty->getValue();
        $servers[$key]['pid'] = 999999;
        $serversProperty->setValue(null, $servers);

        $this->assertFalse(ServerRegistry::has($key), 'ServerRegistry should not have the key from a different process.');
        $this->assertNull(ServerRegistry::get($key), 'Getting the key from a different process should return null.');

    }

    public function testHandlesPortRangeCalculationBasedOnProcessId(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(ServerRegistry::class);
        $method = $reflection->getMethod('findAvailablePortForWorker');
        // Act
        $port1 = $method->invoke(null, 8000);
        $port2 = $method->invoke(null, 8000);
        // Assert
        $this->assertEquals($port1, $port2, 'Ports calculated for the same process should be consistent.');
        $this->assertGreaterThanOrEqual(8000, $port1, 'Calculated port should be within the expected range.');
    }

    public function testGeneratesUniqueProcessIdAcrossResets(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(ServerRegistry::class);
        $method = $reflection->getMethod('getProcessId');
        // Act
        $processId1 = $method->invoke(null);
        // Reset the process ID
        $processIdsProperty = $reflection->getProperty('processId');
        $processIdsProperty->setValue(null, null);

        $processId2 = $method->invoke(null);
        // Assert
        $this->assertNotEquals($processId1, $processId2, 'Process IDs should be unique across resets.');
    }

    public function testHandlesEmptyRegistryGracefully(): void
    {
        $stats = ServerRegistry::getStats();

        $this->assertIsArray($stats);
        $this->assertEquals(0, $stats['total_servers'], 'Total servers should be 0 for an empty registry.');
        $this->assertEquals(0, $stats['active_servers'], 'Active servers should be 0 for an empty registry.');
        $this->assertIsArray($stats['servers'], 'Servers list should be an array.');
        $this->assertEmpty($stats['servers'], 'Servers list should be empty for an empty registry.');
    }

    public function testCalculateUptimeCorrectly(): void
    {
        // Arrange
        $mockServerManager = Mockery::mock(ServerManager::class);
        $mockServerManager->shouldReceive('isRunning')->andReturn(true);
        $mockServerManager->shouldReceive('getUrl')->andReturn('http://127.0.0.1:8000');
        $mockServerManager->shouldReceive('getPort')->andReturn(8000);
        $mockServerManager->shouldReceive('stop')->once();

        $key = 'test_key_' . uniqid();

        // Act
        ServerRegistry::register($key, $mockServerManager);

        usleep(100000); // Sleep for 100ms to simulate uptime

        $stats = ServerRegistry::getStats();
        $uptime = $stats['servers'][$key]['uptime'];
        // Assert
        $this->assertGreaterThanOrEqual(0.1, $uptime, 'Uptime should be at least 0.1 seconds.');
        $this->assertLessThan(5, $uptime, 'Uptime should be less than 5 seconds.');
    }
}
