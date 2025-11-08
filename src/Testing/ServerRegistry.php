<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Testing;

class ServerRegistry
{
    private static array $servers = [];

    private static ?string $processId = null;

    /**
     * Get the unique process identifier.
     */
    protected static function getProcessId(): string
    {
        if (self::$processId === null) {
            // Combine PID and a unique token for this test run
            self::$processId = getmypid() . '_' . uniqid('volttest_', true);
        }

        return self::$processId;
    }

    /**
     * Generate a unique key for a server instance.
     */
    public static function generateKey(string $testClass, string $host = '127.0.0.1'): string
    {
        return md5(self::getProcessId() . '|' . $testClass . '|' . $host);
    }

    /**
     * Register a server manager instance.
     * It handles concurrent registrations safely by locking.
     */
    public static function register(string $key, ServerManager $manager): void
    {
        $lockFile = sys_get_temp_dir() . '/volttest_server_registry.lock';
        $lockHandle = fopen($lockFile, 'c+');
        if ($lockHandle && flock($lockHandle, LOCK_EX)) {
            try {
                self::$servers[$key] = [
                    'manager' => $manager,
                    'pid' => getmypid(),
                    'registered_at' => microtime(true),
                ];
            } finally {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }
    }

    /**
     * Get a server manager instance.
     */
    public static function get(string $key): ?ServerManager
    {
        if (! isset(self::$servers[$key])) {
            return null;
        }

        // Verify it's still from our process
        if (self::$servers[$key]['pid'] !== getmypid()) {
            unset(self::$servers[$key]);

            return null;
        }

        return self::$servers[$key]['manager'];
    }

    /**
     * Check if a server is registered.
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Get or create a server for a test class.
     */
    public static function getOrCreate(
        string $testClass,
        string $basePath,
        bool $debug,
        string $host = '127.0.0.1',
        ?int $preferredPort = null
    ): ServerManager {
        $key = self::generateKey($testClass, $host);

        if ($server = self::get($key)) {
            return $server;
        }

        // Find an available port for this worker
        $port = $preferredPort ?? self::findAvailablePortForWorker();

        $manager = new ServerManager($basePath, $debug, $host, $port);
        self::register($key, $manager);

        return $manager;
    }

    /**
     * Stop a specific server.
     */
    public static function stop(string $key): void
    {
        if ($manager = self::get($key)) {
            $manager->stop();
            unset(self::$servers[$key]);
        }
    }

    /**
     * Stop all servers for this process.
     */
    public static function stopAll(): void
    {
        $currentPid = getmypid();

        foreach (self::$servers as $key => $data) {
            if ($data['pid'] === $currentPid) {
                $data['manager']->stop();
                unset(self::$servers[$key]);
            }
        }
    }

    /**
     * Find an available port for this worker process.
     * Uses a range based on the process ID to minimize collisions.
     */
    protected static function findAvailablePortForWorker(int $basePort = 8000): int
    {
        // Create a port range based on process ID to reduce collisions
        $pid = getmypid();
        $offset = ($pid % 100) * 10; // Each worker gets a 10-port range
        $startPort = $basePort + $offset;

        // Try to find an available port in this worker's range
        for ($port = $startPort; $port < $startPort + 10; $port++) {
            if (! self::isPortInUse($port)) {
                return $port;
            }
        }

        // Fallback: try the general range
        for ($port = $basePort; $port < $basePort + 1000; $port++) {
            if (! self::isPortInUse($port)) {
                return $port;
            }
        }

        throw new \RuntimeException('No available ports found for test server');
    }

    /**
     * Check if a port is in use.
     */
    protected static function isPortInUse(int $port, string $host = '127.0.0.1'): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 0.1);

        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * Get statistics about registered servers.
     */
    public static function getStats(): array
    {
        $currentPid = getmypid();
        $stats = [
            'process_id' => self::getProcessId(),
            'pid' => $currentPid,
            'total_servers' => count(self::$servers),
            'active_servers' => 0,
            'servers' => [],
        ];

        foreach (self::$servers as $key => $data) {
            if ($data['pid'] === $currentPid) {
                $manager = $data['manager'];
                $isRunning = $manager->isRunning();

                if ($isRunning) {
                    $stats['active_servers']++;
                }

                $stats['servers'][$key] = [
                    'running' => $isRunning,
                    'url' => $manager->getUrl(),
                    'port' => $manager->getPort(),
                    'uptime' => microtime(true) - $data['registered_at'],
                ];
            }
        }

        return $stats;
    }

    /**
     * Clean up any orphaned servers from previous runs.
     */
    public static function cleanupOrphaned(): void
    {
        $currentPid = getmypid();

        foreach (self::$servers as $key => $data) {
            // If the server is from a different process or not running, remove it
            if ($data['pid'] !== $currentPid || ! $data['manager']->isRunning()) {
                unset(self::$servers[$key]);
            }
        }
    }

    /**
     * Register shutdown handler to clean up servers.
     */
    public static function registerShutdownHandler(): void
    {
        static $registered = false;

        if (! $registered) {
            register_shutdown_function([self::class, 'stopAll']);
            $registered = true;
        }
    }
}
