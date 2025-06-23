<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Commands;

use Mockery;
use Orchestra\Testbench\TestCase;
use VoltTest\Laravel\Commands\RunVoltTestCommand;
use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Services\ReportGenerator;
use VoltTest\Laravel\Services\TestClassDiscoverer;
use VoltTest\Laravel\Services\TestConfigurationValidator;
use VoltTest\Laravel\Services\TestRunner;
use VoltTest\Laravel\Services\UrlTestCreator;

class RunVoltTestCommandTest extends TestCase
{
    protected TestClassDiscoverer $mockTestDiscoverer;

    protected UrlTestCreator $mockUrlTestCreator;

    protected TestRunner $mockTestRunner;

    protected ReportGenerator $mockReportGenerator;

    protected TestConfigurationValidator $mockValidator;

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


        $this->command = new RunVoltTestCommand(
            $this->mockTestDiscoverer,
            $this->mockUrlTestCreator,
            $this->mockTestRunner,
            $this->mockReportGenerator,
            $this->mockValidator
        );
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
        $app['config']->set('volttest.save_reports', true);
        $app['config']->set('volttest.reports_path', '/tmp/reports');
    }

    /** @test */
    public function it_configures_virtual_users_when_option_provided(): void
    {
        $this->mockValidator
            ->shouldReceive('validateVirtualUsers')
            ->with('20')
            ->once();

        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->with(null)
            ->andReturn(['App\\VoltTests\\UserTest']);

        $this->mockTestRunner
            ->shouldReceive('run')
            ->with(false)
            ->andReturn($this->createMockResult());

        $this->mockReportGenerator
            ->shouldReceive('saveReport')
            ->andReturn('/tmp/report.json');

        $this->artisan('volttest:run', ['--users' => '20'])
            ->expectsOutput('Set virtual users: 20')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_configures_duration_when_option_provided(): void
    {
        $this->mockValidator
            ->shouldReceive('validateVirtualUsers')
            ->with('10')
            ->once();

        $this->mockValidator
            ->shouldReceive('validateDuration')
            ->with('60s')
            ->once();

        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->andReturn(['App\\VoltTests\\UserTest']);

        $this->mockTestRunner
            ->shouldReceive('run')
            ->with(false)
            ->andReturn($this->createMockResult());

        $this->mockReportGenerator
            ->shouldReceive('saveReport')
            ->andReturn('/tmp/report.json');

        $this->artisan('volttest:run', ['--duration' => '60s'])
            ->expectsOutput('Set test duration: 60s')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_enables_debug_mode_when_option_provided(): void
    {
        $this->mockValidator
            ->shouldReceive('validateVirtualUsers')
            ->with('10')
            ->once();

        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->andReturn(['App\\VoltTests\\UserTest']);

        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn($this->createMockResult());

        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run', ['--debug' => true])
            ->expectsOutput('HTTP debugging enabled')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_url_test_when_url_option_provided(): void
    {
        $this->mockValidator
            ->shouldReceive('validateVirtualUsers')
            ->with('10')
            ->once();

        $url = 'https://example.com';
        $mockTestClass = Mockery::mock(VoltTestCase::class);

        $this->mockUrlTestCreator
            ->shouldReceive('isUrl')
            ->with($url)
            ->andReturn(true);

        $this->mockValidator
            ->shouldReceive('validateUrl')
            ->with($url)
            ->once();

        $this->mockValidator
            ->shouldReceive('validateUrlTestOptions')
            ->once();

        $this->mockUrlTestCreator
            ->shouldReceive('createUrlTest')
            ->with($url, Mockery::type('array'))
            ->andReturn($mockTestClass);

        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn($this->createMockResult());

        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run', [
            'test' => $url,
            '--url' => true,
        ])
            ->expectsOutput("Setting up direct URL test for: {$url}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_specific_test_class_when_provided(): void
    {
        $this->mockValidator
            ->shouldReceive('validateVirtualUsers')
            ->with('10')
            ->once();

        $testClass = 'UserTest';

        $this->mockUrlTestCreator
            ->shouldReceive('isUrl')
            ->with($testClass)
            ->andReturn(false);

        $this->mockValidator
            ->shouldReceive('validateTestClassName')
            ->with($testClass)
            ->once();

        $this->mockTestDiscoverer
            ->shouldReceive('resolveTestClass')
            ->with($testClass)
            ->andReturn('App\\VoltTests\\UserTest');

        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn($this->createMockResult());

        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run', ['test' => $testClass])
            ->expectsOutput('Found 1 test class(es).')
            ->expectsOutput('Loading test: App\\VoltTests\\UserTest')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_discovers_all_test_classes_when_no_specific_test_provided(): void
    {
        $this->mockValidator
            ->shouldReceive('validateVirtualUsers')
            ->with('10')
            ->once();

        $testClasses = [
            'App\\VoltTests\\UserTest',
            'App\\VoltTests\\OrderTest',
        ];

        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->with(null)
            ->andReturn($testClasses);

        $this->mockUrlTestCreator
            ->shouldReceive('isUrl')
            ->andReturn(false);

        $this->mockValidator
            ->shouldReceive('validateTestClassName')
            ->andReturnTrue();

        $this->mockTestDiscoverer
            ->shouldReceive('resolveTestClass')
            ->with('App\\VoltTests\\UserTest')
            ->andReturn('App\\VoltTests\\UserTest');

        $this->mockTestDiscoverer
            ->shouldReceive('resolveTestClass')
            ->with('App\\VoltTests\\OrderTest')
            ->andReturn('App\\VoltTests\\OrderTest');

        $this->mockTestRunner
            ->shouldReceive('run')
            ->with(false)
            ->andReturn($this->createMockResult());

        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run')
            ->expectsOutputToContain('Found 2 test class(es).')
            ->expectsOutputToContain('Loading test: App\\VoltTests\\UserTest')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_uses_custom_path_when_provided(): void
    {
        $this->mockValidator
            ->shouldReceive('validateVirtualUsers')
            ->with('10')
            ->once();

        $customPath = 'app/customTests/path';

        $this->mockValidator
            ->shouldReceive('validatePath')
            ->with($customPath)
            ->once();

        $this->mockTestDiscoverer
            ->shouldReceive('findTestClasses')
            ->with($customPath)
            ->andReturn(['App\\CustomTests\\TestClass']);

        $this->mockTestDiscoverer
            ->shouldReceive('resolveTestClass')
            ->with('App\\CustomTests\\TestClass')
            ->andReturn('App\\CustomTests\\TestClass');

        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn($this->createMockResult());

        $this->mockUrlTestCreator->shouldIgnoreMissing();
        $this->mockValidator->shouldReceive('validateTestClassName')->andReturnTrue();
        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run', ['--path' => $customPath])
            ->expectsOutputToContain('Loading test: App\\CustomTests\\TestClass')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_url_test_with_custom_options(): void
    {
        $url = 'https://api.example.com/users';

        $this->mockUrlTestCreator
            ->shouldReceive('isUrl')
            ->with($url)
            ->andReturn(true);

        $this->mockValidator->shouldIgnoreMissing();

        $this->mockUrlTestCreator
            ->shouldReceive('createUrlTest')
            ->with($url, [
                'method' => 'POST',
                'scenario_name' => 'Custom API Test',
                'body' => '{"name":"test"}',
                'content_type' => 'application/json',
                'headers' => ['Authorization' => 'Bearer token'],
                'expected_status_code' => 201,
            ])
            ->andReturn(Mockery::mock(VoltTestCase::class));

        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn($this->createMockResult());

        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run', [
            'test' => $url,
            '--url' => true,
            '--method' => 'POST',
            '--scenario-name' => 'Custom API Test',
            '--body' => '{"name":"test"}',
            '--content-type' => 'application/json',
            '--headers' => '{"Authorization":"Bearer token"}',
            '--code-status' => '201',
        ])
            ->expectsOutput("Setting up direct URL test for: {$url}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_invalid_json_headers_gracefully(): void
    {
        $url = 'https://example.com';

        $this->mockValidator
            ->shouldReceive('validateVirtualUsers')
            ->with('10')
            ->once();



        $this->mockValidator->shouldIgnoreMissing();

        $this->mockUrlTestCreator
            ->shouldReceive('createUrlTest')
            ->with($url, Mockery::on(fn ($options) => empty($options['headers'])))
            ->andReturn(Mockery::mock(VoltTestCase::class));

        $this->mockTestRunner
            ->shouldReceive('run')
            ->andReturn($this->createMockResult());

        $this->mockTestRunner
            ->shouldReceive('run')
            ->with(true)
            ->andReturn($this->createMockResult());

        $this->mockReportGenerator
            ->shouldReceive('saveReport')
            ->andReturn('/tmp/report.json');

        $this->mockReportGenerator->shouldIgnoreMissing();

        $this->artisan('volttest:run', [
            'test' => $url,
            '--url' => true,
            '--headers' => 'invalid json',
        ])->assertExitCode(0);
    }

    protected function createMockResult()
    {
        return new class () {
            public function getDuration()
            {
                return '30s';
            }

            public function getTotalRequests()
            {
                return 100;
            }

            public function getSuccessRate()
            {
                return 95.5;
            }

            public function getRequestsPerSecond()
            {
                return 10.5;
            }

            public function getSuccessRequests()
            {
                return 95;
            }

            public function getFailedRequests()
            {
                return 5;
            }

            public function getMinResponseTime()
            {
                return '10ms';
            }

            public function getMaxResponseTime()
            {
                return '500ms';
            }

            public function getAvgResponseTime()
            {
                return '150ms';
            }

            public function getMedianResponseTime()
            {
                return '140ms';
            }

            public function getP95ResponseTime()
            {
                return '300ms';
            }

            public function getP99ResponseTime()
            {
                return '450ms';
            }

            public function getAllMetrics()
            {
                return [];
            }

            public function getRawOutput()
            {
                return 'Raw test output';
            }
        };
    }
}
