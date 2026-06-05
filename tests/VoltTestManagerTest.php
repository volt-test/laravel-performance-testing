<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests;

use Exception;
use Illuminate\Support\Collection;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Scenarios\LaravelScenario;
use VoltTest\Laravel\VoltTestManager;
use VoltTest\VoltTest;

class VoltTestManagerTest extends TestCase
{
    protected VoltTestManager $manager;

    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'name' => 'Test Application',
            'description' => 'Test description',
            'virtual_users' => 5,
            'duration' => '30s',
            'http_debug' => true,
            'ramp_up' => '10s',
        ];

        $this->manager = new VoltTestManager($this->config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('volttest.base_url', 'http://localhost:8000');
    }

    public function test_it_initializes_with_configuration(): void
    {
        $voltTest = $this->manager->getVoltTest();

        $this->assertInstanceOf(VoltTest::class, $voltTest);
        $this->assertInstanceOf(Collection::class, $this->manager->getScenarios());
        $this->assertTrue($this->manager->getScenarios()->isEmpty());
    }

    public function test_it_creates_scenario_with_name(): void
    {
        $scenario = $this->manager->scenario('Test Scenario');

        $this->assertInstanceOf(LaravelScenario::class, $scenario);
        $this->assertFalse($this->manager->getScenarios()->isEmpty());
    }

    public function test_it_adds_test_from_class_name(): void
    {
        $testClass = new class () implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $manager->scenario('Sample Test');
            }
        };

        $className = get_class($testClass);
        $result = $this->manager->addTestFromClass($className);

        $this->assertSame($this->manager, $result);
        $this->assertFalse($this->manager->getScenarios()->isEmpty());
    }

    public function test_it_adds_test_from_instance(): void
    {
        $testInstance = new class () implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $manager->scenario('Instance Test');
            }
        };

        $result = $this->manager->addTestFromClass($testInstance);

        $this->assertSame($this->manager, $result);
        $this->assertFalse($this->manager->getScenarios()->isEmpty());
    }

    public function test_it_throws_exception_for_non_existent_class(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('VoltTest class NonExistentClass not found.');

        $this->manager->addTestFromClass('NonExistentClass');
    }

    public function test_it_throws_exception_for_invalid_test_class(): void
    {
        $invalidClass = new class () {
            // Does not implement VoltTestCase
        };

        $className = get_class($invalidClass);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Class {$className} must implement VoltTestCase interface.");

        $this->manager->addTestFromClass($className);
    }

    public function test_it_configures_volt_test_with_virtual_users(): void
    {
        $config = ['virtual_users' => 20];
        $manager = new VoltTestManager($config);

        // Note: We can't directly test VoltTest configuration without mocking
        // This test verifies the manager accepts the configuration
        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_it_configures_volt_test_with_duration(): void
    {
        $config = ['duration' => '60s'];
        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_it_configures_volt_test_with_http_debug(): void
    {
        $config = ['http_debug' => true];
        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_it_configures_volt_test_with_ramp_up(): void
    {
        $config = ['ramp_up' => '20s'];
        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_it_handles_empty_configuration(): void
    {
        $manager = new VoltTestManager([]);

        $voltTest = $manager->getVoltTest();
        $this->assertInstanceOf(VoltTest::class, $voltTest);
    }

    public function test_it_handles_partial_configuration(): void
    {
        $config = [
            'name' => 'Partial Test',
            'virtual_users' => 3,
        ];

        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_it_returns_volt_test_instance(): void
    {
        $voltTest = $this->manager->getVoltTest();

        $this->assertInstanceOf(VoltTest::class, $voltTest);
        $this->assertSame($voltTest, $this->manager->getVoltTest());
    }

    public function test_it_returns_scenarios_collection(): void
    {
        $scenarios = $this->manager->getScenarios();

        $this->assertInstanceOf(Collection::class, $scenarios);

        // Add a scenario and verify it's in the collection
        $this->manager->scenario('Test Scenario');
        $this->assertFalse($scenarios->isEmpty());
    }

    public function test_it_can_run_tests(): void
    {
        // Create a simple test
        $testInstance = new class () implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $scenario = $manager->scenario('Simple Test');
                $scenario->step('Test Step')
                    ->get('/test')
                    ->expectStatus(200);
            }
        };

        $this->manager->addTestFromClass($testInstance);

        // Note: Actually running the test would require a real server
        // This test verifies the method exists and can be called
        $this->assertIsCallable([$this->manager, 'run']);
    }

    public function test_it_supports_method_chaining_for_add_test(): void
    {
        $testInstance = new class () implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $manager->scenario('Chainable Test');
            }
        };

        $result = $this->manager
            ->addTestFromClass($testInstance)
            ->addTestFromClass($testInstance);

        $this->assertSame($this->manager, $result);
    }

    public function test_it_uses_default_name_when_not_provided(): void
    {
        $config = ['description' => 'Test description'];
        $manager = new VoltTestManager($config);

        // The VoltTest should be created with default name
        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_it_uses_default_description_when_not_provided(): void
    {
        $config = ['name' => 'Test Application'];
        $manager = new VoltTestManager($config);

        // The VoltTest should be created with default description
        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_it_stores_multiple_scenarios(): void
    {
        $this->manager->scenario('First Scenario');
        $this->manager->scenario('Second Scenario');
        $this->manager->scenario('Third Scenario');

        $scenarios = $this->manager->getScenarios();
        $this->assertCount(3, $scenarios);
    }

    public function test_it_handles_multiple_test_classes(): void
    {
        $firstTest = new class () implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $manager->scenario('First Test Scenario');
            }
        };

        $secondTest = new class () implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $manager->scenario('Second Test Scenario');
            }
        };

        $this->manager
            ->addTestFromClass($firstTest)
            ->addTestFromClass($secondTest);

        $scenarios = $this->manager->getScenarios();
        $this->assertCount(2, $scenarios);
    }

    public function test_stage_returns_self_for_chaining(): void
    {
        $manager = new VoltTestManager([]);

        $result = $manager->stage('1m', 50);

        $this->assertSame($manager, $result);
    }

    public function test_stage_delegates_to_volt_test(): void
    {
        $manager = new VoltTestManager([]);

        $manager->stage('1m', 50)
            ->stage('5m', 100)
            ->stage('1m', 0);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_config_stages_applied_on_construction(): void
    {
        $config = [
            'stages' => [
                ['duration' => '1m', 'target' => 50],
                ['duration' => '5m', 'target' => 100],
                ['duration' => '1m', 'target' => 0],
            ],
        ];

        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_config_stages_override_constant_load(): void
    {
        $config = [
            'virtual_users' => 20,
            'duration' => '30s',
            'stages' => [
                ['duration' => '1m', 'target' => 50],
            ],
        ];

        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_empty_stages_config_uses_constant_load(): void
    {
        $config = [
            'virtual_users' => 20,
            'duration' => '30s',
            'stages' => [],
        ];

        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_regions_returns_self_for_chaining(): void
    {
        $manager = new VoltTestManager([]);

        $result = $manager->regions(['us-east-1' => 100]);

        $this->assertSame($manager, $result);
    }

    public function test_regions_delegates_to_volt_test(): void
    {
        $manager = new VoltTestManager([]);

        $manager->regions(['us-east-1' => 60, 'eu-west-1' => 40]);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_config_regions_applied_on_construction(): void
    {
        $config = [
            'regions' => [
                'us-east-1' => 60,
                'eu-west-1' => 40,
            ],
        ];

        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_empty_regions_config_is_ignored(): void
    {
        $config = [
            'regions' => [],
        ];

        $manager = new VoltTestManager($config);

        $this->assertInstanceOf(VoltTest::class, $manager->getVoltTest());
    }

    public function test_target_returns_self_for_chaining(): void
    {
        $result = $this->manager->target('https://api.example.com');

        $this->assertSame($this->manager, $result);
    }

    public function test_target_sets_url_and_default_idle_timeout(): void
    {
        $this->manager->target('https://api.example.com');

        $config = $this->getVoltTestConfig($this->manager);
        $this->assertEquals('https://api.example.com', $config['target']['url']);
        $this->assertEquals('30s', $config['target']['idle_timeout']);
    }

    public function test_target_sets_url_and_custom_idle_timeout(): void
    {
        $this->manager->target('https://api.example.com', '10s');

        $config = $this->getVoltTestConfig($this->manager);
        $this->assertEquals('https://api.example.com', $config['target']['url']);
        $this->assertEquals('10s', $config['target']['idle_timeout']);
    }

    public function test_target_with_chaining(): void
    {
        $result = $this->manager
            ->target('https://api.example.com', '5s')
            ->name('My Test')
            ->description('My Description');

        $this->assertSame($this->manager, $result);
    }

    public function test_run_auto_sets_target_from_base_url_config(): void
    {
        $config = [
            'base_url' => 'http://localhost:9000',
        ];
        $manager = new VoltTestManager($config);

        $voltTestConfig = $this->getVoltTestConfig($manager);
        $this->assertEquals('https://example.com', $voltTestConfig['target']['url']);

        // After calling run preparation, the target should be set from base_url
        // We verify the targetSet flag is false before run
        $reflection = new \ReflectionClass($manager);
        $prop = $reflection->getProperty('targetSet');
        $prop->setAccessible(true);
        $this->assertFalse($prop->getValue($manager));
    }

    public function test_explicit_target_prevents_auto_fallback(): void
    {
        $config = [
            'base_url' => 'http://localhost:9000',
        ];
        $manager = new VoltTestManager($config);
        $manager->target('https://custom.example.com');

        $reflection = new \ReflectionClass($manager);
        $prop = $reflection->getProperty('targetSet');
        $prop->setAccessible(true);
        $this->assertTrue($prop->getValue($manager));

        $voltTestConfig = $this->getVoltTestConfig($manager);
        $this->assertEquals('https://custom.example.com', $voltTestConfig['target']['url']);
    }

    public function test_test_case_can_set_target(): void
    {
        $testInstance = new class () implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $manager->target('https://myapp.example.com');
                $manager->scenario('Test Scenario');
            }
        };

        $this->manager->addTestFromClass($testInstance);

        $reflection = new \ReflectionClass($this->manager);
        $prop = $reflection->getProperty('targetSet');
        $prop->setAccessible(true);
        $this->assertTrue($prop->getValue($this->manager));
    }

    public function test_target_updates_laravel_base_url_config(): void
    {
        $this->assertEquals('http://localhost:8000', config('volttest.base_url'));

        $this->manager->target('https://httpbin.org');

        $this->assertEquals('https://httpbin.org', config('volttest.base_url'));
    }

    public function test_target_updates_base_url_so_scenarios_use_correct_host(): void
    {
        $this->manager->target('https://httpbin.org');

        $scenario = $this->manager->scenario('Test');
        $scenario->step('Get Home')->get('/get?page=home')->expectStatus(200);

        $scenarios = $this->manager->getScenarios();
        $scenarioArray = $scenarios->first()->getScenario()->toArray();
        $stepUrl = $scenarioArray['steps'][0]['request']['url'];

        $this->assertEquals('https://httpbin.org/get?page=home', $stepUrl);
    }

    public function test_relative_paths_use_default_base_url_without_target(): void
    {
        $scenario = $this->manager->scenario('Test');
        $scenario->step('Get Home')->get('/api/health')->expectStatus(200);

        $scenarios = $this->manager->getScenarios();
        $scenarioArray = $scenarios->first()->getScenario()->toArray();
        $stepUrl = $scenarioArray['steps'][0]['request']['url'];

        $this->assertEquals('http://localhost:8000/api/health', $stepUrl);
    }

    public function test_target_does_not_affect_absolute_urls_in_steps(): void
    {
        $this->manager->target('https://httpbin.org');

        $scenario = $this->manager->scenario('Test');
        $scenario->step('External')->get('https://other-api.com/health')->expectStatus(200);

        $scenarios = $this->manager->getScenarios();
        $scenarioArray = $scenarios->first()->getScenario()->toArray();
        $stepUrl = $scenarioArray['steps'][0]['request']['url'];

        $this->assertEquals('https://other-api.com/health', $stepUrl);
    }

    private function getVoltTestConfig(VoltTestManager $manager): array
    {
        $reflection = new \ReflectionClass($manager->getVoltTest());
        $configProp = $reflection->getProperty('config');
        $configProp->setAccessible(true);

        return $configProp->getValue($manager->getVoltTest())->toArray();
    }

    public function test_name_returns_self_for_chaining(): void
    {
        $result = $this->manager->name('New Name');

        $this->assertSame($this->manager, $result);
    }

    public function test_description_returns_self_for_chaining(): void
    {
        $result = $this->manager->description('New Description');

        $this->assertSame($this->manager, $result);
    }

    public function test_name_and_description_chaining(): void
    {
        $result = $this->manager
            ->name('My Test')
            ->description('My Description');

        $this->assertSame($this->manager, $result);
    }

    public function test_name_delegates_to_volt_test(): void
    {
        $this->manager->name('Updated Name');

        $this->assertInstanceOf(VoltTest::class, $this->manager->getVoltTest());
    }

    public function test_description_delegates_to_volt_test(): void
    {
        $this->manager->description('Updated Description');

        $this->assertInstanceOf(VoltTest::class, $this->manager->getVoltTest());
    }

    public function test_test_case_can_set_name_and_description(): void
    {
        $testInstance = new class () implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $manager->name('Custom Test Name')
                    ->description('Custom Description');
                $manager->scenario('Test Scenario');
            }
        };

        $result = $this->manager->addTestFromClass($testInstance);

        $this->assertSame($this->manager, $result);
        $this->assertFalse($this->manager->getScenarios()->isEmpty());
    }
}
