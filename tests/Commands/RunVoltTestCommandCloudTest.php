<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Commands;

use Mockery;
use Orchestra\Testbench\TestCase;
use VoltTest\CloudRun;
use VoltTest\Laravel\Commands\RunVoltTestCommand;
use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Services\ReportGenerator;
use VoltTest\Laravel\Services\TestClassDiscoverer;
use VoltTest\Laravel\Services\TestConfigurationValidator;
use VoltTest\Laravel\Services\TestRunner;
use VoltTest\Laravel\Services\UrlTestCreator;
use VoltTest\Laravel\VoltTestManager;
use VoltTest\TestResult;

class RunVoltTestCommandCloudTest extends TestCase
{
    protected $mockTestDiscoverer;

    protected $mockUrlTestCreator;

    protected $mockTestRunner;

    protected $mockReportGenerator;

    protected $mockValidator;

    protected RunVoltTestCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockTestDiscoverer = Mockery::mock(TestClassDiscoverer::class);
        $this->mockUrlTestCreator = Mockery::mock(UrlTestCreator::class);
        $this->mockTestRunner = Mockery::mock(TestRunner::class);
        $this->mockReportGenerator = Mockery::mock(ReportGenerator::class);
        $this->mockValidator = Mockery::mock(TestConfigurationValidator::class);

        $this->app->instance(TestClassDiscoverer::class, $this->mockTestDiscoverer);
        $this->app->instance(UrlTestCreator::class, $this->mockUrlTestCreator);
        $this->app->instance(TestRunner::class, $this->mockTestRunner);
        $this->app->instance(ReportGenerator::class, $this->mockReportGenerator);
        $this->app->instance(TestConfigurationValidator::class, $this->mockValidator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \VoltTest\Laravel\VoltTestServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('volttest.save_reports', false);
        $app['config']->set('volttest.cloud.enabled', false);
        $app['config']->set('volttest.cloud.api_key', 'vt_test_key_123');
    }

    public function test_cloud_flag_enables_cloud_mode(): void
    {
        $mockManager = Mockery::mock(VoltTestManager::class);
        $mockManager->shouldReceive('getVoltTest')
            ->andReturn(Mockery::mock(\VoltTest\VoltTest::class)->shouldIgnoreMissing());
        $mockManager->shouldReceive('cloud')
            ->once()
            ->andReturnSelf();
        $mockManager->shouldReceive('addTestFromClass')
            ->andReturnSelf();
        $mockManager->shouldReceive('run')
            ->andReturn(new CloudRun('run-1', 'test-1', 'completed'));

        $this->app->instance('laravel-volttest', $mockManager);

        $this->mockValidator->shouldIgnoreMissing();
        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->andReturn(['App\\VoltTests\\UserTest']);
        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn(new CloudRun('run-1', 'test-1', 'completed'));

        $this->artisan('volttest:run', ['--cloud' => true])
            ->expectsOutput('Cloud execution mode enabled.')
            ->assertExitCode(0);
    }

    public function test_cloud_config_enables_cloud_without_flag(): void
    {
        $this->app['config']->set('volttest.cloud.enabled', true);

        $mockManager = Mockery::mock(VoltTestManager::class);
        $mockManager->shouldReceive('getVoltTest')
            ->andReturn(Mockery::mock(\VoltTest\VoltTest::class)->shouldIgnoreMissing());
        $mockManager->shouldReceive('cloud')
            ->once()
            ->andReturnSelf();
        $mockManager->shouldReceive('addTestFromClass')
            ->andReturnSelf();
        $mockManager->shouldReceive('run')
            ->andReturn(new CloudRun('run-1', 'test-1', 'completed'));

        $this->app->instance('laravel-volttest', $mockManager);

        $this->mockValidator->shouldIgnoreMissing();
        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->andReturn(['App\\VoltTests\\UserTest']);
        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn(new CloudRun('run-1', 'test-1', 'completed'));

        $this->artisan('volttest:run')
            ->expectsOutput('Cloud execution mode enabled.')
            ->assertExitCode(0);
    }

    public function test_no_cloud_when_flag_absent_and_config_disabled(): void
    {
        $mockManager = Mockery::mock(VoltTestManager::class);
        $mockManager->shouldReceive('getVoltTest')
            ->andReturn(Mockery::mock(\VoltTest\VoltTest::class)->shouldIgnoreMissing());
        $mockManager->shouldNotReceive('cloud');
        $mockManager->shouldReceive('addTestFromClass')
            ->andReturnSelf();
        $mockManager->shouldReceive('run')
            ->andReturn($this->createMockResult());

        $this->app->instance('laravel-volttest', $mockManager);

        $this->mockValidator->shouldIgnoreMissing();
        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->andReturn(['App\\VoltTests\\UserTest']);
        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn($this->createMockResult());
        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run')
            ->assertExitCode(0);
    }

    public function test_skips_report_for_cloud_run_result(): void
    {
        $mockManager = Mockery::mock(VoltTestManager::class);
        $mockManager->shouldReceive('getVoltTest')
            ->andReturn(Mockery::mock(\VoltTest\VoltTest::class)->shouldIgnoreMissing());
        $mockManager->shouldReceive('cloud')->andReturnSelf();
        $mockManager->shouldReceive('addTestFromClass')->andReturnSelf();
        $mockManager->shouldReceive('run')
            ->andReturn(new CloudRun('run-1', 'test-1', 'completed'));

        $this->app->instance('laravel-volttest', $mockManager);

        $this->mockValidator->shouldIgnoreMissing();
        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->andReturn(['App\\VoltTests\\UserTest']);
        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn(new CloudRun('run-1', 'test-1', 'completed'));

        $this->mockReportGenerator->shouldNotReceive('displaySummary');
        $this->mockReportGenerator->shouldNotReceive('saveReport');

        $this->artisan('volttest:run', ['--cloud' => true])
            ->assertExitCode(0);
    }

    public function test_generates_report_for_test_result(): void
    {
        $this->app['config']->set('volttest.save_reports', true);

        $mockManager = Mockery::mock(VoltTestManager::class);
        $mockManager->shouldReceive('getVoltTest')
            ->andReturn(Mockery::mock(\VoltTest\VoltTest::class)->shouldIgnoreMissing());
        $mockManager->shouldReceive('addTestFromClass')->andReturnSelf();
        $mockManager->shouldReceive('run')->andReturn($this->createMockResult());

        $this->app->instance('laravel-volttest', $mockManager);

        $this->mockValidator->shouldIgnoreMissing();
        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->andReturn(['App\\VoltTests\\UserTest']);
        $this->mockTestRunner
            ->shouldReceive('run')
            ->with(false)
            ->andReturn($this->createMockResult());
        $this->mockReportGenerator
            ->shouldReceive('displaySummary')
            ->once();
        $this->mockReportGenerator
            ->shouldReceive('saveReport')
            ->once()
            ->andReturn('/tmp/report.json');

        $this->artisan('volttest:run')
            ->assertExitCode(0);
    }

    public function test_cloud_with_url_test(): void
    {
        $url = 'https://example.com';

        $mockManager = Mockery::mock(VoltTestManager::class);
        $mockManager->shouldReceive('getVoltTest')
            ->andReturn(Mockery::mock(\VoltTest\VoltTest::class)->shouldIgnoreMissing());
        $mockManager->shouldReceive('cloud')->once()->andReturnSelf();
        $mockManager->shouldReceive('addTestFromClass')->andReturnSelf();
        $mockManager->shouldReceive('run')
            ->andReturn(new CloudRun('run-1', 'test-1', 'completed'));

        $this->app->instance('laravel-volttest', $mockManager);

        $this->mockValidator->shouldIgnoreMissing();
        $this->mockUrlTestCreator
            ->shouldReceive('isUrl')
            ->with($url)
            ->andReturn(true);
        $this->mockUrlTestCreator
            ->shouldReceive('createUrlTest')
            ->with($url, Mockery::type('array'))
            ->andReturn(Mockery::mock(VoltTestCase::class));
        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn(new CloudRun('run-1', 'test-1', 'completed'));
        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run', [
            'test' => $url,
            '--url' => true,
            '--cloud' => true,
        ])
            ->expectsOutput('Cloud execution mode enabled.')
            ->expectsOutput("Setting up direct URL test for: {$url}")
            ->assertExitCode(0);
    }

    public function test_cloud_error_handled_gracefully(): void
    {
        $mockManager = Mockery::mock(VoltTestManager::class);
        $mockManager->shouldReceive('getVoltTest')
            ->andReturn(Mockery::mock(\VoltTest\VoltTest::class)->shouldIgnoreMissing());
        $mockManager->shouldReceive('cloud')
            ->andThrow(new \VoltTest\Exceptions\VoltTestException(
                'Cloud API key is required. Set VOLTTEST_API_KEY in your .env file.'
            ));

        $this->app->instance('laravel-volttest', $mockManager);

        $this->mockValidator->shouldIgnoreMissing();
        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->andReturn(['App\\VoltTests\\UserTest']);

        $this->artisan('volttest:run', ['--cloud' => true])
            ->expectsOutputToContain('Cloud API key is required')
            ->assertExitCode(0);
    }

    protected function createMockResult(): TestResult
    {
        $output = <<<'EOT'
Test Metrics Summary:
===================
Duration:     1.5s
Total Reqs:   10
Success Rate: 100.00%
Req/sec:      6.67
Success Requests: 10
Failed Requests: 0

Response Time:
------------
Min:    50ms
Max:    200ms
Avg:    100ms
Median: 95ms
P95:    180ms
P99:    195ms
EOT;

        return new TestResult($output);
    }
}
