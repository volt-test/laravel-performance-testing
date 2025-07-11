<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use Illuminate\Support\Str;

class StepCodeGenerator
{
    /**
     * Generate step code for the given routes.
     *
     * @param array $routes
     * @return string
     */
    public function generate(array $routes): string
    {
        if (empty($routes)) {
            return '// No routes selected';
        }

        $code = [];

        foreach ($routes as $index => $route) {
            $stepName = $this->getStepNameFromRoute($route);
            $method = $this->getPrimaryMethod($route['methods']);
            $uri = $this->replaceRouteParameters($route['uri']);
            $type = $route['type'] ?? 'web';

            $code[] = $this->generateStepCode($index + 1, $stepName, $method, $uri, $type);
        }

        return implode("\n\n", $code);
    }

    /**
     * Extracts a step name from the route definition.
     *
     * @param array $route
     * @return string
     */
    protected function getStepNameFromRoute(array $route): string
    {
        if (! empty($route['name'])) {
            return Str::studly($route['name']);
        }

        $name = str_replace(['/', '{', '}', '-', '.'], ' ', $route['uri']);

        return Str::studly($name);
    }

    /**
     * Get the primary HTTP method for the route.
     *
     * @param array $methods
     * @return string
     */
    protected function getPrimaryMethod(array $methods): string
    {
        $preferred = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($preferred as $method) {
            if (in_array($method, $methods, true)) {
                return $method;
            }
        }

        return $methods[0] ?? 'GET';
    }

    /**
     * Replace route parameters in the URI with VoltTest placeholders.
     *
     * @param string $uri
     * @return string
     */
    protected function replaceRouteParameters(string $uri): string
    {
        return preg_replace_callback('/\{([^}]+)\}/', fn ($m) => '${' . rtrim($m[1], '?') . '}', $uri);
    }

    /**
     * Generate the code for a single step.
     *
     * @param int $stepNumber
     * @param string $stepName
     * @param string $method
     * @param string $uri
     * @param string $type
     * @return string
     */
    protected function generateStepCode(int $stepNumber, string $stepName, string $method, string $uri, string $type): string
    {
        $method = strtolower($method);
        $fullUrl = '/' . ltrim($uri, '/');
        $isWeb = $type === 'web';

        // Headers as third parameter for automatic content type detection
        $headers = $isWeb
            ? ", ['Content-Type' => 'application/x-www-form-urlencoded']"
            : ", ['Authorization' => 'Bearer \${token}', 'Content-Type' => 'application/json', 'Accept' => 'application/json']";

        $csrf = $isWeb ? "            '_token' => '\${csrf_token}',\n" : '';

        $body = $isWeb
            ? "            '_method' => '$method',\n"
            : '';

        return match ($method) {
            'post' => <<<PHP
        // Step $stepNumber : $stepName
        \$scenario->step('$stepName')
            ->post('$fullUrl', [
$csrf                // Add form fields here
            ]$headers)
            ->expectStatus(200);
PHP,
            'put', 'patch' => <<<PHP
        // Step $stepNumber : $stepName
        \$scenario->step('$stepName')
            ->$method('$fullUrl', [
$csrf$body                // Add form fields here
            ]$headers)
            ->expectStatus(200);
PHP,
            'delete' => <<<PHP
        // Step $stepNumber : $stepName
        \$scenario->step('$stepName')
            ->delete('$fullUrl'$headers)
            ->expectStatus(200);
PHP,
            default => <<<PHP
        // Step $stepNumber : $stepName
        \$scenario->step('$stepName')
            ->get('$fullUrl'$headers)
            ->expectStatus(200);
PHP
        };
    }
}
