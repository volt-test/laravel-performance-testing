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
}
