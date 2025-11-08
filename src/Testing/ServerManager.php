<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Testing;

use Symfony\Component\Process\Process;

class ServerManager
{
    protected ?Process $serverProcess = null;

    protected string $host;

    protected int $port;

    protected string $basePath;

    protected bool $debug = false;

    public function __construct(string $basePath, bool $debug, string $host = '127.0.0.1', int $port = 8000)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->host = $host;
        $this->port = $port;
        $this->debug = $debug;

        $this->validateLaravelStructure();
    }

    /**
     * Validate that the base path contains a valid Laravel application.
     */
    protected function validateLaravelStructure(): void
    {
        $requiredFiles = [
            '/bootstrap/app.php' => 'Bootstrap file',
            '/public' => 'Public directory',
        ];

        foreach ($requiredFiles as $path => $description) {
            $fullPath = $this->basePath . $path;
            if (! file_exists($fullPath)) {
                throw new \RuntimeException(
                    "Invalid Laravel application structure: {$description} not found at {$fullPath}"
                );
            }
        }

        // Check if public/index.php exists
        if (! file_exists($this->basePath . '/public/index.php')) {
            throw new \RuntimeException(
                "Laravel index.php not found at: {$this->basePath}/public/index.php"
            );
        }
    }

    /**
     * Start the PHP development server.
     */
    public function start(): void
    {
        if ($this->isRunning()) {
            return;
        }

        // Validate base path
        $publicPath = $this->basePath . '/public';
        if (! is_dir($publicPath)) {
            throw new \RuntimeException(
                "Public directory not found at: {$publicPath}"
            );
        }

        // Check for server.php or use public/index.php
        $routerFile = null;
        if (file_exists($this->basePath . '/server.php')) {
            $routerFile = $this->basePath . '/server.php';
        }


        $availablePort = $this->findAvailablePort($this->port);
        if ($availablePort !== $this->port) {
            if ($this->debug) {
                echo "Port {$this->port} in use, using {$availablePort}\n";
            }
            $this->port = $availablePort;
        }

        $command = [
            PHP_BINARY,
            '-S',
            "{$this->host}:{$this->port}",
            '-t',
            realpath($publicPath),
        ];
        // Add router file if available
        if ($routerFile) {
            $command[] = realpath($routerFile);
        }
        if ($this->debug) {
            echo "Starting server with command: " . implode(' ', $command) . "\n";
            echo "Working directory: {$this->basePath}\n";
            echo "Public path: " . realpath($publicPath) . "\n";
        }
        $this->serverProcess = new Process($command, $this->basePath);
        $this->serverProcess->start();

        try {
            $this->waitForServer();
        } catch (\RuntimeException $e) {
            $output = $this->getOutput();

            throw new \RuntimeException(
                "Server failed to start: {$e->getMessage()}\nOutput: {$output}"
            );
        }
    }

    /**
     * Stop the PHP development server.
     */
    public function stop(int $timeout = 5): void
    {
        if (! $this->isRunning()) {
            return;
        }

        $start = time();
        $this->serverProcess->stop($timeout, SIGTERM);
        while ($this->serverProcess->isRunning() && (time() - $start) < $timeout) {
            usleep(100000); // 0.1 second
        }

        // Force kill if still running
        if ($this->serverProcess->isRunning()) {
            $this->serverProcess->stop(0, SIGKILL);

            if ($this->debug) {
                echo "Server process force killed after timeout\n";
            }
        }

        $this->serverProcess = null;
    }

    /**
     * Get the server URL.
     */
    public function getUrl(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    /**
     * Get the server port.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Find an available port.
     */
    protected function findAvailablePort(int $startPort = 8000): int
    {
        for ($port = $startPort; $port < $startPort + 100; $port++) {
            $connection = @fsockopen($this->host, $port, $errno, $errstr, 0.1);

            if (! is_resource($connection)) {
                return $port; // Port is available
            }

            fclose($connection);
        }

        throw new \RuntimeException('No available ports found');
    }

    /**
     * Check if server is running.
     */
    public function isRunning(): bool
    {
        return $this->serverProcess && $this->serverProcess->isRunning();
    }

    /**
     * Wait for server to be ready.
     */
    protected function waitForServer(int $timeout = 10): void
    {
        $start = time();
        $lastError = '';
        $attempts = 0;
        $maxBackoff = 500000; // 0.5 seconds
        while (time() - $start < $timeout) {
            try {
                if ($this->canConnect()) {
                    if ($this->debug) {
                        echo "Server is ready after {$attempts} attempts\n";
                    }

                    return;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
            }

            // Check if process died
            if (! $this->serverProcess->isRunning()) {
                throw new \RuntimeException(
                    "Server process died unexpectedly.\n" .
                    "Output: " . $this->getOutput()
                );
            }
            $attempts++;
            // Exponential backoff: 100ms, 200ms, 400ms, 500ms (max)
            $backoff = min(100000 * pow(2, $attempts - 1), $maxBackoff);
            usleep($backoff);
        }

        throw new \RuntimeException(
            "Server failed to start within {$timeout} seconds.\n" .
            "Attempts: {$attempts}\n" .
            "Last error: {$lastError}\n" .
            "Output: " . $this->getOutput()
        );
    }

    /**
     * Check if Laravel application is ready.
     */
    protected function canConnect(): bool
    {
        // Try multiple endpoints to verify server is ready
        $endpoints = [
            '/',
            '/api/health',
            '/__volttest_health',
        ];
        foreach ($endpoints as $endpoint) {
            $url = $this->getUrl() . $endpoint;
            $context = stream_context_create([
                'http' => [
                    'timeout' => 1,
                    'ignore_errors' => true,
                    'follow_location' => 0,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response !== false && isset($http_response_header[0])) {
                // Accept 2xx, 3xx, 404, 405
                if (preg_match('/HTTP\/\d\.\d\s+[2345]\d{2}/', $http_response_header[0])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get server process output.
     */
    public function getOutput(): string
    {
        if (! $this->serverProcess) {
            return '';
        }

        return $this->serverProcess->getOutput() . $this->serverProcess->getErrorOutput();
    }

    /**
     * Detect if a server is already running on the given host and port.
     *
     * @param string $host
     * @param int $port
     * @return bool
     */
    public static function detectRunningServer(string $host = '127.0.0.1', int $port = 8000): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, 1);
        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * Get detailed server status
     */
    public function getStatus(): array
    {
        return [
            'running' => $this->isRunning(),
            'url' => $this->getUrl(),
            'port' => $this->port,
            'pid' => $this->serverProcess?->getPid(),
        ];
    }

    public function __destruct()
    {
        $this->stop();
    }
}
