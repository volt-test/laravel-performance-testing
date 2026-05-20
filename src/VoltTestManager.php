<?php

declare(strict_types=1);

namespace VoltTest\Laravel;

use Exception;
use Illuminate\Support\Collection;
use VoltTest\CloudRun;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Scenarios\LaravelScenario;
use VoltTest\TestResult;
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
     * Add a stage to the load profile.
     *
     * @param string $duration Duration of this stage (e.g. "5m", "30s", "1h")
     * @param int $target Target VU count at the end of this stage
     * @return $this
     * @throws VoltTestException
     */
    public function stage(string $duration, int $target): self
    {
        $this->voltTest->stage($duration, $target);

        return $this;
    }

    /**
     * Replace the VoltTest instance with a fresh one (no load profile).
     * Use before adding stages when the default config set constant load.
     *
     * @return $this
     */
    public function resetLoadProfile(): self
    {
        $this->voltTest = new VoltTest(
            $this->config['name'] ?? 'Laravel Application Test',
            $this->config['description'] ?? 'Performance test for Laravel application'
        );

        if (isset($this->config['http_debug'])) {
            $this->voltTest->setHttpDebug($this->config['http_debug']);
        }

        return $this;
    }

    /**
     * Set region distribution for cloud execution.
     *
     * @param array<string, int> $regions Region code => weight (e.g., ['us-east-1' => 60, 'eu-west-1' => 40])
     * @return $this
     * @throws VoltTestException
     */
    public function regions(array $regions): self
    {
        $this->voltTest->regions($regions);

        return $this;
    }

    /**
     * Enable cloud execution mode.
     *
     * @return $this
     *
     * @throws VoltTestException
     */
    public function cloud(): self
    {
        $apiKey = $this->config['cloud']['api_key'] ?? null;

        if (empty($apiKey)) {
            throw new VoltTestException(
                'Cloud API key is required. Set VOLTTEST_API_KEY in your .env file.'
            );
        }

        $this->voltTest->cloud($apiKey);

        return $this;
    }

    /**
     * Run The Test
     *
     * @param bool $streamOutput
     *
     * @return TestResult|CloudRun|null
     * */
    public function run(bool $streamOutput = false): TestResult|CloudRun|null
    {
        return $this->voltTest->run($streamOutput);
    }

    /**
     * Set a custom conflict prompt callback for when a test with the same name already exists.
     *
     * @param callable $callback Receives the existing test array, must return 'update' or 'create'
     * @return $this
     */
    public function setOnConflictPrompt(callable $callback): self
    {
        $this->voltTest->setOnConflictPrompt($callback);

        return $this;
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
        if (! empty($this->config['stages']) && is_array($this->config['stages'])) {
            foreach ($this->config['stages'] as $stage) {
                $this->voltTest->stage($stage['duration'], $stage['target']);
            }
        } else {
            if (isset($this->config['virtual_users'])) {
                $this->voltTest->setVirtualUsers($this->config['virtual_users']);
            }

            if (isset($this->config['duration'])) {
                $this->voltTest->setDuration($this->config['duration']);
            }

            if (isset($this->config['ramp_up'])) {
                $this->voltTest->setRampUp($this->config['ramp_up']);
            }
        }

        if (isset($this->config['http_debug'])) {
            $this->voltTest->setHttpDebug($this->config['http_debug']);
        }

        if (! empty($this->config['regions']) && is_array($this->config['regions'])) {
            $this->voltTest->regions($this->config['regions']);
        }
    }
}
