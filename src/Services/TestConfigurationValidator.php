<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use InvalidArgumentException;

class TestConfigurationValidator
{
    /**
     * Validate virtual users configuration.
     */
    public function validateVirtualUsers(?string $users): void
    {
        if ($users === null) {
            return;
        }

        $usersInt = (int) $users;

        if ($usersInt < 1) {
            throw new InvalidArgumentException('Virtual users must be a positive integer greater than 0');
        }
    }

    /**
     * Validate duration format.
     */
    public function validateDuration(?string $duration): void
    {
        if ($duration === null) {
            return;
        }

        if (! preg_match('/^\d+[smh]$/', $duration)) {
            throw new InvalidArgumentException(
                'Duration must be in format: number followed by s (seconds), m (minutes), or h (hours). Example: 30s, 5m, 1h'
            );
        }
    }

    /**
     * Validate HTTP method for URL tests.
     */
    public function validateHttpMethod(string $method): void
    {
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

        if (! in_array(strtoupper($method), $allowedMethods, true)) {
            throw new InvalidArgumentException(
                'HTTP method must be one of: ' . implode(', ', $allowedMethods)
            );
        }
    }

    /**
     * Validate JSON string.
     */
    public function validateJsonString(?string $json): void
    {
        if ($json === null || $json === '') {
            return;
        }

        try {
            json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON format: ' . $e->getMessage());
        }
    }

    /**
     * Validate URL format.
     */
    public function validateUrl(string $url): void
    {
        // Normalize URL first
        if (! preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL format: {$url}");
        }

        // Additional checks for common issues
        $parsedUrl = parse_url($url);

        if (! isset($parsedUrl['host'])) {
            throw new InvalidArgumentException("URL must contain a valid host: {$url}");
        }

        // Check for localhost without explicit protocol in production
        if (app()->environment('production') &&
            in_array($parsedUrl['host'], ['localhost', '127.0.0.1'], true) &&
            (! isset($parsedUrl['scheme']) || $parsedUrl['scheme'] === 'http')) {

            throw new InvalidArgumentException(
                'Testing localhost URLs in production environment may not work as expected'
            );
        }
    }

    /**
     * Validate test class name format.
     */
    public function validateTestClassName(string $className): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $className)) {
            throw new InvalidArgumentException(
                'Test class name must start with a letter or underscore and contain only letters, numbers, and underscores'
            );
        }

        if (strlen($className) > 100) {
            throw new InvalidArgumentException('Test class name is too long (maximum 100 characters)');
        }
    }

    /**
     * Validate file path.
     */
    public function validatePath(?string $path): void
    {
        if ($path === null) {
            return;
        }

        // Check for directory traversal attempts
        if (str_contains($path, '..')) {
            throw new InvalidArgumentException('Path cannot contain ".." for security reasons');
        }

        // Check for absolute paths outside of project
        if (str_starts_with($path, '/') && ! str_starts_with($path, base_path())) {
            throw new InvalidArgumentException('Absolute paths outside of project directory are not allowed');
        }
    }

    /**
     * Validate content type.
     */
    public function validateContentType(?string $contentType): void
    {
        if ($contentType === null) {
            return;
        }

        $validContentTypes = [
            'application/json',
            'application/xml',
            'application/x-www-form-urlencoded',
            'multipart/form-data',
            'text/plain',
            'text/html',
            'text/xml',
        ];

        $isValid = false;
        foreach ($validContentTypes as $validType) {
            if (str_starts_with(strtolower($contentType), $validType)) {
                $isValid = true;

                break;
            }
        }

        if (! $isValid) {
            throw new InvalidArgumentException(
                'Content type must be one of the common types: ' . implode(', ', $validContentTypes)
            );
        }
    }

    /**
     * Validate all URL test options at once.
     */
    public function validateUrlTestOptions(array $options): void
    {
        if (isset($options['method'])) {
            $this->validateHttpMethod($options['method']);
        }

        if (isset($options['content_type'])) {
            $this->validateContentType($options['content_type']);
        }


        if (isset($options['expected_status_code'])) {
            if (
                ! is_numeric($options['expected_status_code']) ||
                (int)$options['expected_status_code'] < 100 ||
                (int)$options['expected_status_code'] > 599
            ) {
                throw new InvalidArgumentException('Expected status code must be a valid HTTP status code (100-599)');
            }
        }
    }
}
