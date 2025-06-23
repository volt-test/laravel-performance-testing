<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Scenarios\LaravelScenario;
use VoltTest\Laravel\VoltTestManager;

class UrlTestCreator
{
    /**
     * Check if the given string is a URL.
     * @param string|null $test
     * @return bool
     */
    public function isUrl(?string $test): bool
    {
        if (! $test) {
            return false;
        }

        return filter_var($test, FILTER_VALIDATE_URL) !== false ||
            preg_match('/^https?:\/\//', $test) ||
            preg_match('/^[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}/', $test);
    }

    /**
     * Create a dynamic test class for URL testing.
     */
    public function createUrlTest(string $url, array $options): VoltTestCase
    {
        $url = $this->normalizeUrl($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid URL provided: {$url}");
        }

        return new class ($url, $options) implements VoltTestCase {
            public function __construct(
                private string $url,
                private array $options
            ) {
            }

            public function define(VoltTestManager $manager): void
            {
                $scenarioName = $this->options['scenario_name'] ?? 'URL Load Test';
                $scenario = $manager->scenario($scenarioName);

                $method = strtolower($this->options['method']);
                $headers = $this->options['headers'] ?? [];
                $body = $this->options['body'] ?? '';
                $contentType = $this->options['content_type'] ?? '';

                $step = $scenario->step("Load test {$this->url}");

                // Add content type if specified
                if ($contentType) {
                    $step->header('Content-Type', $contentType);
                }

                // Add custom headers
                foreach ($headers as $name => $value) {
                    $step->header($name, $value);
                }

                // Perform the HTTP request based on method
                $this->performHttpRequest($step, $method, $body);

                // Check for expected status code
                if (isset($this->options['expected_status_code'])) {
                    $this->performHttpStatusCheck($step, (int)$this->options['expected_status_code']);
                } else {
                    $this->performHttpStatusCheck($step);
                }
            }

            private function performHttpRequest(LaravelScenario $step, string $method, string $body): void
            {
                match ($method) {
                    'post' => $step->post($this->url, $body),
                    'put' => $step->put($this->url, $body),
                    'patch' => $step->patch($this->url, $body),
                    'delete' => $step->delete($this->url),
                    default => $step->get($this->url)
                };
            }

            private function performHttpStatusCheck(LaravelScenario $step, int $codeStatus = 200): void
            {
                $step->expectStatus($codeStatus);
            }
        };
    }

    /**
     * Normalize URL by adding protocol if missing.
     */
    protected function normalizeUrl(string $url): string
    {
        if (! preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }

        return $url;
    }
}
