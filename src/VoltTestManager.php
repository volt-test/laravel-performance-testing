<?php

declare(strict_types=1);

namespace VoltTest\Laravel;

use Exception;
use Illuminate\Support\Collection;
use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Scenarios\LaravelScenario;
use VoltTest\VoltTest;

class VoltTestManager
{
    /**
     * Configuration array
     *
     * @var array
     * */
    protected array $config;

    /**
     * Core VoltTest instance
     * @var VoltTest
     * */
    protected VoltTest $voltTest;

    /**
     * Test Scenarios
     *
     * @var Collection
     * */
    protected Collection $scenarios;

    /**
     * Create a new VoltTestManager instance
     *
     * @param array $config
     * */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->scenarios = new Collection();
        $this->voltTest = new VoltTest(
            $config['name'] ?? 'Laravel Application Test',
            $config['description'] ?? 'Performance test for Laravel application'
        );

        // Configure the VoltTest instance
        $this->configureVoltTest();
    }

    /**
     * Add a test scenario from a class name.
     *
     * @param string|VoltTestCase $className
     * @return $this
     * @throws Exception
     */
    public function addTestFromClass(string|VoltTestCase $className): self
    {
        if (is_string($className)) {
            if (! class_exists($className)) {
                throw new Exception(sprintf('VoltTest class %s not found.', $className));
            }

            $instance = new $className();

            if (! $instance instanceof VoltTestCase) {
                throw new Exception(sprintf('Class %s must implement VoltTestCase interface.', $className));
            }
        } else {
            $instance = $className;
        }

        // Allow the test to define itself
        $instance->define($this);

        return $this;
    }

    /**
     * Add a test Scenario from a class name
     *
     * @param string $name
     * @return LaravelScenario
     */
    public function scenario(string $name): LaravelScenario
    {
        $voltTestScenario = $this->voltTest->scenario($name);

        $scenario = new LaravelScenario($voltTestScenario);

        $this->scenarios->push($scenario);

        return $scenario;
    }

    /**
     * Run The Test
     *
     * @param bool $streamOutput
     *
     * @return mixed
     * */
    public function run(bool $streamOutput = false): mixed
    {
        return $this->voltTest->run($streamOutput);
    }

    /**
     * Get the underlying VoltTest instance.
     *
     * @return VoltTest
     */
    public function getVoltTest(): VoltTest
    {
        return $this->voltTest;
    }

    /**
     * Get all registered scenarios.
     *
     * @return Collection
     */
    public function getScenarios(): Collection
    {
        return $this->scenarios;
    }

    /**
     * Configure the VoltTest instance based on the provided configuration.
     *
     * @return void
     */
    protected function configureVoltTest(): void
    {
        if (isset($this->config['virtual_users'])) {
            $this->voltTest->setVirtualUsers($this->config['virtual_users']);
        }

        if (isset($this->config['duration'])) {
            $this->voltTest->setDuration($this->config['duration']);
        }

        if (isset($this->config['http_debug'])) {
            $this->voltTest->setHttpDebug($this->config['http_debug']);
        }

        if (isset($this->config['ramp_up'])) {
            $this->voltTest->setRampUp($this->config['ramp_up']);
        }
    }
}
