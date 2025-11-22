<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Scenarios;

use Orchestra\Testbench\TestCase;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Laravel\Scenarios\LaravelScenario;
use VoltTest\Laravel\VoltTestServiceProvider;
use VoltTest\Scenario;

class LaravelScenarioCsvTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [VoltTestServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('volttest.csv_data', [
            'path' => __DIR__ . '/fixtures',
            'validate_files' => true,
            'default_distribution' => 'unique',
            'default_headers' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create test fixtures directory
        $fixturesDir = __DIR__ . '/fixtures';
        if (! is_dir($fixturesDir)) {
            mkdir($fixturesDir, 0755, true);
        }

        // Create a test CSV file
        $csvContent = "email,password,user_id,name\n";
        $csvContent .= "user1@example.com,password123,1,John Doe\n";
        $csvContent .= "user2@example.com,password456,2,Jane Smith\n";
        $csvContent .= "user3@example.com,password789,3,Bob Wilson\n";

        file_put_contents($fixturesDir . '/users.csv', $csvContent);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $fixturesDir = __DIR__ . '/fixtures';
        if (is_dir($fixturesDir)) {
            array_map('unlink', glob($fixturesDir . '/*'));
            rmdir($fixturesDir);
        }

        parent::tearDown();
    }

    public function testDataSourceWithRelativePathUsesConfigDefaults(): void
    {
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));

        $result = $scenario->dataSource('users.csv');

        $this->assertInstanceOf(LaravelScenario::class, $result);
        $this->assertSame($scenario, $result);
    }

    public function testDataSourceWithCustomDistributionMode(): void
    {
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));

        $result = $scenario->dataSource('users.csv', 'random');

        $this->assertInstanceOf(LaravelScenario::class, $result);
    }

    public function testDataSourceWithCustomHeadersSetting(): void
    {
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));

        $result = $scenario->dataSource('users.csv', 'sequential', false);

        $this->assertInstanceOf(LaravelScenario::class, $result);
    }

    public function testDataSourceWithAbsolutePath(): void
    {
        $absolutePath = __DIR__ . '/fixtures/users.csv';
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));

        $result = $scenario->dataSource($absolutePath);

        $this->assertInstanceOf(LaravelScenario::class, $result);
    }

    public function testDataSourceThrowsExceptionForNonexistentFile(): void
    {
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage("CSV data source file");

        $scenario->dataSource('nonexistent.csv');
    }

    public function testDataSourceWithValidationDisabled(): void
    {
        config(['volttest.csv_data.validate_files' => false]);

        // Create a temporary file that exists
        $tempFile = __DIR__ . '/fixtures/temp.csv';
        file_put_contents($tempFile, "id,name\n1,test\n");

        $scenario = new LaravelScenario(new Scenario('Test Scenario'));

        // Should work with existing file even when validation is disabled
        $result = $scenario->dataSource('temp.csv');

        // Clean up
        unlink($tempFile);

        $this->assertInstanceOf(LaravelScenario::class, $result);
    }

    public function testDataSourceThrowsExceptionWhenCalledTwice(): void
    {
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));

        $scenario->dataSource('users.csv');

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Data source configuration already set');

        $scenario->dataSource('users.csv');
    }

    public function testResolveCsvFilePathWithRelativePath(): void
    {
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));
        $reflection = new \ReflectionClass($scenario);
        $method = $reflection->getMethod('resolveCsvFilePath');
        $method->setAccessible(true);

        $csvConfig = ['path' => '/custom/path'];
        $result = $method->invoke($scenario, 'test.csv', $csvConfig);

        $this->assertEquals('/custom/path/test.csv', $result);
    }

    public function testResolveCsvFilePathWithAbsolutePath(): void
    {
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));
        $reflection = new \ReflectionClass($scenario);
        $method = $reflection->getMethod('resolveCsvFilePath');
        $method->setAccessible(true);

        $csvConfig = ['path' => '/custom/path'];
        $result = $method->invoke($scenario, '/absolute/path/test.csv', $csvConfig);

        $this->assertEquals('/absolute/path/test.csv', $result);
    }

    public function testScenarioFluentInterfaceWithDataSource(): void
    {
        $scenario = new LaravelScenario(new Scenario('Test Scenario'));

        $result = $scenario
            ->dataSource('users.csv', 'unique', true)
            ->step('Login')
            ->post('/login', ['email' => '${email}', 'password' => '${password}'])
            ->expectStatus(200);

        $this->assertInstanceOf(LaravelScenario::class, $result);
    }
}
