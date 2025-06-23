<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Services;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VoltTest\Laravel\Services\TestConfigurationValidator;

class TestConfigurationValidatorTest extends TestCase
{
    protected TestConfigurationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TestConfigurationValidator();
    }

    /** @test */
    public function it_validates_virtual_users_successfully(): void
    {
        // Valid cases should not throw exceptions
        $this->validator->validateVirtualUsers('1');
        $this->validator->validateVirtualUsers('10');
        $this->validator->validateVirtualUsers('100');
        $this->validator->validateVirtualUsers(null);

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_rejects_invalid_virtual_users(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Virtual users must be a positive integer greater than 0');

        $this->validator->validateVirtualUsers('0');
    }

    /** @test */
    public function it_rejects_negative_virtual_users(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Virtual users must be a positive integer greater than 0');

        $this->validator->validateVirtualUsers('-1');
    }

    /** @test */
    public function it_validates_duration_formats_successfully(): void
    {
        // Valid duration formats
        $validDurations = ['30s', '5m', '2h', '1s', '60m', '24h'];

        foreach ($validDurations as $duration) {
            $this->validator->validateDuration($duration);
        }

        // Null should also be valid
        $this->validator->validateDuration(null);

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_rejects_invalid_duration_formats(): void
    {
        $invalidDurations = [
            '30',      // Missing unit
            '30sec',   // Wrong unit
            's30',     // Wrong order
            '30 s',    // Space
            '30.5s',   // Decimal
            'abc',     // Non-numeric
            '',        // Empty
        ];

        foreach ($invalidDurations as $duration) {
            try {
                $this->validator->validateDuration($duration);
                $this->fail("Expected InvalidArgumentException for duration: {$duration}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Duration must be in format', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_http_methods_successfully(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

        foreach ($validMethods as $method) {
            $this->validator->validateHttpMethod($method);
            $this->validator->validateHttpMethod(strtolower($method)); // Test case insensitive
        }

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_rejects_invalid_http_methods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTP method must be one of');

        $this->validator->validateHttpMethod('INVALID');
    }

    /** @test */
    public function it_validates_json_strings_successfully(): void
    {
        $validJsonStrings = [
            '{"key": "value"}',
            '[]',
            '{}',
            '"string"',
            '123',
            'true',
            'false',
            'null',
            null, // null should be valid
            '', // empty string should be valid
        ];

        foreach ($validJsonStrings as $json) {
            $this->validator->validateJsonString($json);
        }

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_rejects_invalid_json_strings(): void
    {
        $invalidJsonStrings = [
            '{invalid json}',
            '{"key": value}', // unquoted value
            '{"key": }', // missing value
            '{key: "value"}', // unquoted key
            '{"key": "value",}', // trailing comma
        ];

        foreach ($invalidJsonStrings as $json) {
            try {
                $this->validator->validateJsonString($json);
                $this->fail("Expected InvalidArgumentException for JSON: {$json}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid JSON format', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_test_class_names_successfully(): void
    {
        $validClassNames = [
            'UserTest',
            'OrderTest',
            'API_Test',
            'TestClass',
            '_ValidTest',
            'Test123',
            'a', // Single character
        ];

        foreach ($validClassNames as $className) {
            $this->validator->validateTestClassName($className);
        }

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_rejects_invalid_test_class_names(): void
    {
        $invalidClassNames = [
            '123Test', // Starts with number
            'Test-Name', // Contains hyphen
            'Test Name', // Contains space
            'Test@Name', // Contains special character
            '', // Empty
            str_repeat('A', 101), // Too long
        ];

        foreach ($invalidClassNames as $className) {
            try {
                $this->validator->validateTestClassName($className);
                $this->fail("Expected InvalidArgumentException for class name: {$className}");
            } catch (InvalidArgumentException $e) {
                $this->assertTrue(
                    str_contains($e->getMessage(), 'Test class name must start') ||
                    str_contains($e->getMessage(), 'Test class name is too long')
                );
            }
        }
    }

    /** @test */
    public function it_validates_file_paths_successfully(): void
    {
        $validPaths = [
            'tests/VoltTests',
            'app/VoltTests',
            'custom/path',
            null, // null should be valid
        ];

        foreach ($validPaths as $path) {
            $this->validator->validatePath($path);
        }

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_rejects_paths_with_directory_traversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot contain ".." for security reasons');

        $this->validator->validatePath('../malicious/path');
    }

    /** @test */
    public function it_validates_content_types_successfully(): void
    {
        $validContentTypes = [
            'application/json',
            'application/xml',
            'application/x-www-form-urlencoded',
            'multipart/form-data',
            'text/plain',
            'text/html',
            'text/xml',
            'application/json; charset=utf-8', // With charset
            null, // null should be valid
        ];

        foreach ($validContentTypes as $contentType) {
            $this->validator->validateContentType($contentType);
        }

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_rejects_invalid_content_types(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type must be one of the common types');

        $this->validator->validateContentType('invalid/content-type');
    }

    /** @test */
    public function it_validates_url_test_options_successfully(): void
    {
        $validOptions = [
            'method' => 'POST',
            'content_type' => 'application/json',
            'scenario_name' => 'Test Scenario',
            'expected_status_code' => 200,
        ];

        $this->validator->validateUrlTestOptions($validOptions);

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_rejects_invalid_status_codes_in_url_options(): void
    {
        $invalidOptions = [
            ['expected_status_code' => 99], // Too low
            ['expected_status_code' => 600], // Too high
            ['expected_status_code' => 'invalid'], // Non-numeric
        ];

        foreach ($invalidOptions as $options) {
            try {
                $this->validator->validateUrlTestOptions($options);
                $this->fail("Expected InvalidArgumentException for status code: " . $options['expected_status_code']);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Expected status code must be a valid HTTP status code', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_edge_case_status_codes(): void
    {
        $validStatusCodes = [100, 200, 300, 404, 500, 599];

        foreach ($validStatusCodes as $code) {
            $this->validator->validateUrlTestOptions(['expected_status_code' => $code]);
        }

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_validates_complex_json_structures(): void
    {
        $complexJson = json_encode([
            'user' => [
                'name' => 'John Doe',
                'preferences' => [
                    'notifications' => true,
                    'theme' => 'dark',
                ],
            ],
            'metadata' => [
                'version' => '1.0',
                'timestamp' => time(),
            ],
        ]);

        $this->validator->validateJsonString($complexJson);

        $this->assertTrue(true); // No exceptions thrown
    }

    /** @test */
    public function it_handles_unicode_in_json(): void
    {
        $unicodeJson = json_encode(['message' => 'Hello 🌍']);

        $this->validator->validateJsonString($unicodeJson);

        $this->assertTrue(true); // No exceptions thrown
    }
}
