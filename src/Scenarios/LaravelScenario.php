<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Scenarios;

use RuntimeException;
use VoltTest\DataSourceConfiguration;
use VoltTest\Exceptions\InvalidJsonPathException;
use VoltTest\Exceptions\InvalidRegexException;
use VoltTest\Exceptions\InvalidRequestValidationException;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Scenario;
use VoltTest\Step;

class LaravelScenario
{
    /**
     * VoltTest Scenario instance
     *
     * @var Scenario
     * */
    protected Scenario $scenario;

    /**
     * Current step in the scenario
     *
     * @var Step|null
     * */
    protected Step|null $currentStep = null;

    public function __construct(Scenario $scenario)
    {
        $this->scenario = $scenario;

        // By default, we'll automatically handle cookies for laravel sessions
        $this->scenario->autohandleCookies();
    }

    /**
     * Create a new step in the scenario
     *
     * @param string $name
     * @return $this
     * */
    public function step(string $name): self
    {
        $this->currentStep = $this->scenario->step($name);

        return $this;
    }

    /**
     * Send a Get request
     *
     * @param string $url
     * @param array<string, string> $headers Key-value array of request headers
     * @return $this
     * @throws InvalidRequestValidationException
     */
    public function get(string $url, array $headers = []): self
    {
        $this->ensureStepExists();
        $this->currentStep->get($this->getUrl($url));
        $this->addHeaders($headers);

        return $this;
    }

    protected function ensureStepExists(): void
    {
        if ($this->currentStep === null) {
            throw new RuntimeException('No step defined. Please define a step before calling this method.');
        }
    }

    /**
     * Get full URL based on configuration.
     *
     * @param string $path
     * @return string
     */
    private function getUrl(string $path): string
    {
        if (! config('volttest.base_url')) {
            return $path;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (! str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $baseUrl = rtrim(config('volttest.base_url'), '/');

        return $baseUrl . $path;
    }

    /**
     * Add headers to the current step.
     *
     * @param array $headers
     * @return void
     * @throws InvalidRequestValidationException
     */
    private function addHeaders(array $headers): void
    {
        foreach ($headers as $key => $value) {
            $this->currentStep->header($key, $value);
        }
    }

    /**
     * Add a custom header.
     *
     * @param string $name
     * @param string $value
     * @return $this
     * @throws InvalidRequestValidationException
     */
    public function header(string $name, string $value): self
    {
        $this->ensureStepExists();
        $this->currentStep->header($name, $value);

        return $this;
    }

    /**
     * Ensure a step exists before calling it
     * @param string $variableName
     * @param string $selector
     * @param string $field
     * @return self
     * @throw \RuntimeException
     */
    public function extractCsrfToken(
        string $variableName = 'csrf_token',
        string $selector = 'input[name=_token]',
        string $field = 'value'
    ): self {
        $this->ensureStepExists();
        $this->extractHtml($variableName, $selector, $field);

        return $this;
    }

    /**
     * Send a POST request.
     *
     * @param string $url
     * @param array|string $data
     * @param array $headers
     * @return $this
     * @throws InvalidRequestValidationException
     */
    public function post(string $url, array|string $data = '', array $headers = []): self
    {
        $this->ensureStepExists();

        if (is_array($data)) {
            $data = implode('&', array_map(
                fn ($key, $value) => "$key=$value",
                array_keys($data),
                $data
            ));
        }

        $this->currentStep->post($this->getUrl($url), $data);
        $this->addHeaders($headers);

        return $this;
    }

    /**
     * Send a PUT request.
     *
     * @param string $url
     * @param array|string $data
     * @param array $headers
     * @return $this
     * @throws InvalidRequestValidationException
     */
    public function put(string $url, array|string $data = '', array $headers = []): self
    {
        $this->ensureStepExists();

        if (is_array($data)) {
            $data = implode('&', array_map(
                fn ($key, $value) => "$key=$value",
                array_keys($data),
                $data
            ));
        }

        $this->currentStep->put($this->getUrl($url), $data);
        $this->addHeaders($headers);

        return $this;
    }

    /**
     * Send a PATCH request.
     *
     * @param string $url
     * @param array|string $data
     * @param array $headers
     * @return $this
     * @throws InvalidRequestValidationException
     */
    public function patch(string $url, array|string $data = '', array $headers = []): self
    {
        $this->ensureStepExists();

        if (is_array($data)) {
            $data = implode('&', array_map(
                fn ($key, $value) => "$key=$value",
                array_keys($data),
                $data
            ));
        }

        $this->currentStep->patch($this->getUrl($url), $data);
        $this->addHeaders($headers);

        return $this;
    }

    /**
     * Send a DELETE request.
     *
     * @param string $url
     * @param array $headers
     * @return $this
     * @throws InvalidRequestValidationException
     */
    public function delete(string $url, array $headers = []): self
    {
        $this->ensureStepExists();
        $this->currentStep->delete($this->getUrl($url));
        $this->addHeaders($headers);

        return $this;
    }

    /**
     * Expect a specific status code.
     *
     * @param int $statusCode
     * @param string|null $name
     * @return $this
     */
    public function expectStatus(int $statusCode, ?string $name = null): self
    {
        $this->ensureStepExists();

        $name = $name ?: 'expect_status_' . $statusCode;
        $this->currentStep->validateStatus($name, $statusCode);

        return $this;
    }

    /**
     * Extract a value from a response using JSON path.
     *
     * @param string $variableName
     * @param string $jsonPath
     * @return $this
     * @throws InvalidJsonPathException
     */
    public function extractJson(string $variableName, string $jsonPath): self
    {
        $this->ensureStepExists();
        $this->currentStep->extractFromJson($variableName, $jsonPath);

        return $this;
    }

    /**
     * Extract a value from a response header.
     *
     * @param string $variableName
     * @param string $headerName
     * @return $this
     * @throws VoltTestException
     */
    public function extractHeader(string $variableName, string $headerName): self
    {
        $this->ensureStepExists();
        $this->currentStep->extractFromHeader($variableName, $headerName);

        return $this;
    }

    /**
     * Extract a value from a response HTML body
     *
     * @param string $variableName
     * @param string $selector
     * @param string|null $attribute
     * @return LaravelScenario
     */
    public function extractHtml(string $variableName, string $selector, ?string $attribute = null): self
    {
        $this->ensureStepExists();
        $this->currentStep->extractFromHtml($variableName, $selector, $attribute);

        return $this;
    }

    /**
     * Extract a value using a regex pattern.
     *
     * @param string $variableName
     * @param string $pattern
     * @return $this
     * @throws InvalidRegexException
     */
    public function extractRegex(string $variableName, string $pattern): self
    {
        $this->ensureStepExists();
        $this->currentStep->extractFromRegex($variableName, $pattern);

        return $this;
    }

    /**
     * Set the think time after this step.
     *
     * @param string $thinkTime
     * @return $this
     * @throws VoltTestException
     */
    public function thinkTime(string $thinkTime): self
    {
        $this->ensureStepExists();
        $this->currentStep->setThinkTime($thinkTime);

        return $this;
    }

    /**
     * Set the weight of this scenario.
     *
     * @param int $weight
     * @return $this
     */
    public function weight(int $weight): self
    {
        $this->scenario->setWeight($weight);

        return $this;
    }

    /**
     * Get The Scenario
     *
     * @return Scenario
     * */
    public function getScenario(): Scenario
    {
        return $this->scenario;
    }

    /**
     * Configure a CSV data source for this scenario.
     *
     * @param string $filename CSV filename (relative to csv_data.path config or absolute path)
     * @param string|null $distribution Distribution mode: 'unique', 'random', or 'sequential'
     * @param bool|null $hasHeaders Whether CSV has header row
     * @return $this
     * @throws VoltTestException
     */
    public function dataSource(string $filename, ?string $distribution = null, ?bool $hasHeaders = null): self
    {
        $csvConfig = config('volttest.csv_data', []);

        // Use defaults from config if not specified
        $distribution = $distribution ?? ($csvConfig['default_distribution'] ?? 'unique');
        $hasHeaders = $hasHeaders ?? ($csvConfig['default_headers'] ?? true);

        // Resolve file path
        $filePath = $this->resolveCsvFilePath($filename, $csvConfig);

        // Validate file exists if validation is enabled
        $validateFiles = $csvConfig['validate_files'] ?? true;
        if ($validateFiles && !file_exists($filePath)) {
            throw new VoltTestException("CSV data source file '{$filePath}' does not exist");
        }

        // Create and set data source configuration
        // Note: DataSourceConfiguration will validate the file again, but only if it exists
        $dataSourceConfig = new DataSourceConfiguration($filePath, $distribution, $hasHeaders);
        $this->scenario->setDataSourceConfiguration($dataSourceConfig);

        return $this;
    }

    /**
     * Resolve the full path to a CSV file.
     *
     * @param string $filename
     * @param array $csvConfig
     * @return string
     */
    private function resolveCsvFilePath(string $filename, array $csvConfig): string
    {
        // If absolute path, use as-is
        if (str_starts_with($filename, '/') || preg_match('/^[A-Z]:/i', $filename)) {
            return $filename;
        }

        // Otherwise, use relative to configured path
        $basePath = $csvConfig['path'] ?? storage_path('volttest/data');

        return $basePath . DIRECTORY_SEPARATOR . $filename;
    }
}
