# PHPUnit Integration Guide

Run VoltTest performance tests within PHPUnit test suites with automated server management and comprehensive assertions.

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [IntegrationVoltTestCase](#integrationvolttestcase)
- [Server Management](#server-management)
- [Performance Assertions](#performance-assertions)
- [Complete Examples](#complete-examples)
- [Configuration](#configuration)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Overview

The PHPUnit integration allows you to:

- Run performance tests as part of your PHPUnit test suite
- Automatically manage test server lifecycle
- Use specialized performance assertions
- Combine functional and performance testing
- Run tests in CI/CD pipelines

## Requirements

- PHP 8.2+
- PHPUnit 10.0+
- ext-pcntl (for server management) and run volt-test engine.

## Quick Start

### 1. Create a PHPUnit Test

```php
<?php

namespace Tests\Performance;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Testing\IntegrationVoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class HomePagePerformanceTest extends IntegrationVoltTestCase
{
    /**
     * Test homepage performance under load.
     */
    public function test_homepage_performance(): void
    {
        $this->loadTestUrl('/', [
            'virtual_users' => 10,
            'stream' => true, // make output visible in console
        ]);

        // Assert performance metrics
        $this->assertVTSuccessful($result, 95.0); // At least 95% success rate for requests
        $this->assertVTP95ResponseTime($result, 500); // 95% of requests complete within 500ms or less
        $this->assertVTAverageResponseTime($result, 200); // Average response time under 200ms
    }
}
```

### 2. Run the Test

```bash
./vendor/bin/phpunit tests/Performance/HomePagePerformanceTest.php
```

## IntegrationVoltTestCase

The `IntegrationVoltTestCase` base class provides everything needed for PHPUnit integration.

### Basic Usage

```php
use VoltTest\Laravel\Testing\IntegrationVoltTestCase;

class MyPerformanceTest extends IntegrationVoltTestCase
{
    public function test_api_endpoint(): void
    {
        $result = $this->loadTestApi('/api/data', 'GET', [], [
            'virtual_users' => 15,
            'duration' => '1m',
        ]);

        $this->assertVTSuccessful($result); // Default 95% success rate
        $this->assertVTP95ResponseTime($result, 400); // 95% of requests within 400ms
    }
}
```

### Available Methods

#### Running VoltTests

**`runVoltTest(VoltTestCase $testClass, array $options = [])`**

Run a VoltTest performance test.
You can reuse existing `VoltTestCase` classes with already makes from commands `php artisan volttest:make`.

```php
$result = $this->runVoltTest($testClass, [
    'virtual_users' => 20,
    'duration' => '1m',
    'ramp_up' => '10s',
    'stream' => false,
    'http_debug' => false,
]);
```

**Options:**
- `virtual_users` - Number of concurrent users (default: 5)
- `duration` - Test duration (e.g., '30s', '2m', '1h')
- `ramp_up` - Gradual user ramp-up time
- `stream` - Stream output to console (default: false)
- `http_debug` - Enable HTTP debugging (default: false)

#### Quick Load Testing Helpers

**`loadTestUrl(string $url, array $options = [])`**

Quick helper to test a single URL.

```php
public function test_homepage_load(): void
{
    $result = $this->loadTestUrl('/', [
        'virtual_users' => 10,
        'duration' => '30s',
    ]);

    $this->assertVTSuccessful($result);
}
```

**`loadTestApi(string $endpoint, string $method = 'GET', array $data = [], array $options = [])`**

Quick helper to test API endpoints.

```php
public function test_api_performance(): void
{
    $result = $this->loadTestApi('/api/users', 'POST', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ], [
        'virtual_users' => 15,
    ]);

    $this->assertVTSuccessful($result);
    $this->assertVTP99ResponseTime($result, 1000);
}
```

#### Configuration Methods

```php

**`setBaseUrl(string $url)`**

Set custom base URL for testing.
Useful if the server is running on a different host/port.

```php
protected function setUp(): void
{
    parent::setUp();
    $this->setBaseUrl('http://localhost:8080');
}
```

**`getBaseUrl()`**

Get the current base URL.

```php
$url = $this->getBaseUrl();
```


## Server Management

The test base class can automatically manage a PHP development server for your tests.

### Enabling Server Management

Set the `$enableServerManagement` property:

```php
class MyPerformanceTest extends IntegrationVoltTestCase
{
    protected static bool $enableServerManagement = true;
    protected static ?int $preferredPort = 8000;

    public function test_with_auto_server(): void
    {
        // Server is automatically started before tests
        // and stopped after tests complete
        $result = $this->loadTestUrl('/');
        $this->assertVTSuccessful($result);
    }
}
```

Or use environment variable:

```bash
VOLTTEST_ENABLE_SERVER_MANAGEMENT=true ./vendor/bin/phpunit
```

### Configuration Options

**`$enableServerManagement`** - Enable automatic server management (default: false)

```php
protected static bool $enableServerManagement = true;
```

**`$preferredPort`** - Preferred port for the test server (default: 8000)

```php
protected static ?int $preferredPort = 9000;
```

**`$enableDebugForServerManagement`** - Enable debug output for server management (default: false)

```php
protected static bool $enableDebugForServerManagement = true;
```

### Environment Variables

- `VOLTTEST_ENABLE_SERVER_MANAGEMENT` - Enable server management
- `VOLTTEST_DEBUG_FOR_SERVER_MANAGEMENT` - Enable server debug output
- `VOLTTEST_BASE_PATH` - Custom Laravel application base path
- `VOLTTEST_HTTP_DEBUG` - Enable HTTP debugging

## Performance Assertions

The `VoltTestAssertions` trait provides specialized assertions for performance testing.

### Success Rate Assertions

**`assertVTSuccessful(TestResult $result, float $minSuccessRate = 95.0)`**

Assert that the test was successful with minimum success rate.

```php
$this->assertVTSuccessful($result);           // >= 95%
$this->assertVTSuccessful($result, 99.0);     // >= 99%
```

**`assertVTErrorRate(TestResult $result, float $maxErrorRate = 5.0)`**

Assert that error rate is below threshold.

```php
$this->assertVTErrorRate($result);            // <= 5%
$this->assertVTErrorRate($result, 1.0);       // <= 1%
```

### Response Time Assertions

**`assertVTMinResponseTime(TestResult $result, int $minTimeMs)`**

Assert minimum response time (useful for detecting unrealistic/cached responses).

```php
$this->assertVTMinResponseTime($result, 10);  // Min >= 10ms
```

**`assertVTMaxResponseTime(TestResult $result, int $maxTimeMs)`**

Assert maximum response time is within threshold.

```php
$this->assertVTMaxResponseTime($result, 2000); // Max <= 2000ms
```

**`assertVTAverageResponseTime(TestResult $result, int $maxAvgTimeMs)`**

Assert average response time is acceptable.

```php
$this->assertVTAverageResponseTime($result, 300); // Avg <= 300ms
```

**`assertVTMedianResponseTime(TestResult $result, int $maxMedianTimeMs)`**

Assert median (P50) response time - represents typical user experience.

```php
$this->assertVTMedianResponseTime($result, 200); // P50 <= 200ms
```

**`assertVTP95ResponseTime(TestResult $result, int $maxP95TimeMs)`**

Assert P95 response time - 95% of requests complete within this time.

```php
$this->assertVTP95ResponseTime($result, 500); // P95 <= 500ms
```

**`assertVTP99ResponseTime(TestResult $result, int $maxP99TimeMs)`**

Assert P99 response time - 99% of requests complete within this time.

```php
$this->assertVTP99ResponseTime($result, 1000); // P99 <= 1000ms
```

### Throughput Assertions

**`assertVTMinimumRequests(TestResult $result, int $minRequests)`**

Assert total requests meet minimum threshold.

```php
$this->assertVTMinimumRequests($result, 100); // Total >= 100
```

**`assertVTMinimumRPS(TestResult $result, float $minRPS)`**

Assert minimum requests per second.

```php
$this->assertVTMinimumRPS($result, 10.0); // RPS >= 10
```

**`assertVTMaximumRPS(TestResult $result, float $maxRPS)`**

Assert maximum requests per second (detect unrealistic results).

```php
$this->assertVTMaximumRPS($result, 1000.0); // RPS <= 1000
```

## Complete Examples

### Example 1: Homepage Performance Test

```php
<?php

namespace Tests\Performance;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Testing\IntegrationVoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class HomePageTest extends IntegrationVoltTestCase
{
    protected static bool $enableServerManagement = true;

    public function test_homepage_handles_moderate_load(): void
    {
        $testClass = new class implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $scenario = $manager->scenario('Homepage Load Test');

                $scenario->step('Load Homepage')
                    ->get('/')
                    ->expectStatus(200);
            }
        };

        $result = $this->runVoltTest($testClass, [
            'virtual_users' => 50,
            'duration' => '1m',
            'ramp_up' => '10s',
        ]);

        // Comprehensive assertions
        $this->assertVTSuccessful($result, 95.0);
        $this->assertVTErrorRate($result, 5.0);
        $this->assertVTAverageResponseTime($result, 200);
        $this->assertVTMedianResponseTime($result, 150);
        $this->assertVTP95ResponseTime($result, 500);
        $this->assertVTP99ResponseTime($result, 1000);
        $this->assertVTMinimumRPS($result, 10.0);
    }
}
```

### Example 2: API Authentication Performance

```php
<?php

namespace Tests\Performance;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Testing\IntegrationVoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class ApiAuthenticationTest extends IntegrationVoltTestCase
{
    protected static bool $enableServerManagement = true;

    public function test_api_login_performance(): void
    {
        $testClass = new class implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $scenario = $manager->scenario('API Login Test')
                    ->dataSource('users.csv', 'unique');

                $scenario->step('Login')
                    ->post('/api/login', [
                        'email' => '${email}',
                        'password' => '${password}',
                    ], [
                        'Content-Type' => 'application/json',
                    ])
                    ->expectStatus(200)
                    ->extractJson('token', 'data.token');

                $scenario->step('Get User Profile')
                    ->get('/api/user', [
                        'Authorization' => 'Bearer ${token}',
                    ])
                    ->expectStatus(200);
            }
        };

        $result = $this->runVoltTest($testClass, [
            'virtual_users' => 20,
            'duration' => '30s',
        ]);

        $this->assertVTSuccessful($result, 98.0);
        $this->assertVTP95ResponseTime($result, 400);
    }
}
```

### Example 3: E-commerce Checkout Flow

```php
<?php

namespace Tests\Performance;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\Testing\IntegrationVoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class CheckoutFlowTest extends IntegrationVoltTestCase
{
    protected static bool $enableServerManagement = true;

    public function test_checkout_flow_performance(): void
    {
        $testClass = new class implements VoltTestCase {
            public function define(VoltTestManager $manager): void
            {
                $scenario = $manager->scenario('Checkout Flow');

                // Browse products
                $scenario->step('Browse Products')
                    ->get('/products')
                    ->expectStatus(200)
                    ->extractHtml('product_id', '.product:first-child', 'data-id');

                // Add to cart
                $scenario->step('Add to Cart')
                    ->post('/cart/add', [
                        'product_id' => '${product_id}',
                        'quantity' => 1,
                    ])
                    ->expectStatus(200)
                    ->thinkTime('2s');

                // Checkout
                $scenario->step('Checkout')
                    ->get('/checkout')
                    ->expectStatus(200)
                    ->extractCsrfToken('token');

                // Complete order
                $scenario->step('Complete Order')
                    ->post('/checkout/complete', [
                        '_token' => '${token}',
                        'payment_method' => 'credit_card',
                    ])
                    ->expectStatus(302);
            }
        };

        $result = $this->runVoltTest($testClass, [
            'virtual_users' => 15,
            'duration' => '2m',
        ]);

        // Assert end-to-end performance
        $this->assertVTSuccessful($result, 95.0);
        $this->assertVTMaxResponseTime($result, 3000);
        $this->assertVTAverageResponseTime($result, 500);
    }
}
```

### Example 4: Quick Load Testing

```php
<?php

namespace Tests\Performance;

use VoltTest\Laravel\Testing\IntegrationVoltTestCase;

class QuickLoadTests extends IntegrationVoltTestCase
{
    protected static bool $enableServerManagement = true;

    public function test_homepage_quick_load(): void
    {
        $result = $this->loadTestUrl('/');
        $this->assertVTSuccessful($result);
    }

    public function test_api_users_endpoint(): void
    {
        $result = $this->loadTestApi('/api/users', 'GET');
        $this->assertVTSuccessful($result);
        $this->assertVTP95ResponseTime($result, 300);
    }

    public function test_api_create_user(): void
    {
        $result = $this->loadTestApi('/api/users', 'POST', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->assertVTSuccessful($result);
    }
}
```

### Example 5: Reusing Existing VoltTest Classes

One of the most powerful patterns is reusing your existing VoltTest classes in PHPUnit tests. This allows you to:
- Define scenarios once in `app/VoltTests/`
- Run them via Artisan commands: `php artisan volttest:run RegistrationTest`
- Run them in PHPUnit test suites with assertions
- Maintain consistency across different test environments

**VoltTest Class** (`app/VoltTests/RegistrationTest.php`):
```php
<?php

namespace App\VoltTests;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class RegistrationTest implements VoltTestCase
{
    public function define(VoltTestManager $manager): void
    {
        $scenario = $manager->scenario('RegistrationTest')
            ->dataSource('registration_users.csv', 'sequential');

        // Step 1: Home
        $scenario->step('Home')
            ->get('/')
            ->expectStatus(200);

        // Step 2: Get Registration Page
        $scenario->step('Register')
            ->get('/register')
            ->extractCsrfToken('csrf_token')
            ->expectStatus(200);

        // Step 3: Submit Registration
        $scenario->step('Submit Registration')
            ->post('/register', [
                '_token' => '${csrf_token}',
                'name' => '${name}',
                'email' => '${email}',
                'password' => '${password}',
                'password_confirmation' => '${password}',
            ], [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])
            ->expectStatus(302);

        // Step 4: Visit Dashboard
        $scenario->step('Visit Dashboard')
            ->get('/dashboard')
            ->expectStatus(200);
    }
}
```

**PHPUnit Test** (`tests/Performance/RegistrationPerformanceTest.php`):
```php
<?php

namespace Tests\Performance;

use App\VoltTests\RegistrationTest;
use VoltTest\Laravel\Testing\IntegrationVoltTestCase;

class RegistrationPerformanceTest extends IntegrationVoltTestCase
{
    protected static bool $enableServerManagement = true;

    public function test_registration_flow_performance(): void
    {
        // Simply instantiate and run the existing VoltTest class
        $test = new RegistrationTest();

        $result = $this->runVoltTest($test, [
            'virtual_users' => 50,
            'duration' => '2m',
            'stream' => true,
        ]);

        // Add comprehensive assertions
        $this->assertVTSuccessful($result, 95.0);
        $this->assertVTErrorRate($result, 5.0);
        $this->assertVTMinResponseTime($result, 10);
        $this->assertVTMaxResponseTime($result, 2000);
        $this->assertVTAverageResponseTime($result, 800);
        $this->assertVTMedianResponseTime($result, 600);
        $this->assertVTP95ResponseTime($result, 1500);
        $this->assertVTP99ResponseTime($result, 1800);
    }

    public function test_registration_handles_high_load(): void
    {
        // Reuse the same test with different parameters
        $test = new RegistrationTest();

        $result = $this->runVoltTest($test, [
            'virtual_users' => 100,
            'duration' => '5m',
            'ramp_up' => '1m',
        ]);

        // More lenient thresholds for higher load
        $this->assertVTSuccessful($result, 90.0);
        $this->assertVTP95ResponseTime($result, 3000);
    }
}
```

**Benefits of This Pattern:**
- **DRY Principle** - Define scenarios once, use everywhere
- **Consistency** - Same tests run in different contexts
- **Flexibility** - Different virtual users, durations, and assertions per test
- **Maintainability** - Update scenario logic in one place
- **Development Workflow** - Use Artisan commands during development, PHPUnit in CI/CD

**Usage:**
```bash
# Run via Artisan (quick testing during development)
php artisan volttest:run RegistrationTest --users=10 --stream

# Run via PHPUnit (with assertions and in CI/CD)
./vendor/bin/phpunit tests/Performance/RegistrationPerformanceTest.php
```

## Configuration

### phpunit.xml Configuration

Add performance tests to a separate test suite:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Performance">
            <directory>tests/Performance</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="VOLTTEST_ENABLE_SERVER_MANAGEMENT" value="true"/>
        <env name="VOLTTEST_BASE_URL" value="http://localhost:8000"/>
        <env name="VOLTTEST_HTTP_DEBUG" value="false"/>
    </php>
</phpunit>
```

### Run Specific Test Suite

```bash
# Run only performance tests
./vendor/bin/phpunit --testsuite=Performance

# Run with custom configuration
./vendor/bin/phpunit --testsuite=Performance --configuration=phpunit.performance.xml
```

## Best Practices

### 1. Organize Performance Tests

Create a dedicated `tests/Performance` directory:

```
tests/
├── Feature/
├── Unit/
└── Performance/
    ├── Api/
    │   ├── AuthenticationTest.php
    │   └── UserApiTest.php
    ├── Web/
    │   ├── HomePageTest.php
    │   └── CheckoutTest.php
    └── Database/
        └── QueryPerformanceTest.php
```

### 2. Use Realistic Load Patterns

```php
// Good: Gradual ramp-up with realistic user count
$result = $this->runVoltTest($testClass, [
    'virtual_users' => 20,
    'duration' => '2m',
    'ramp_up' => '30s',
]);

// Avoid: Sudden spike without ramp-up
$result = $this->runVoltTest($testClass, [
    'virtual_users' => 100,
    'duration' => '10s',
]);
```

### 3. Set Appropriate Thresholds

```php
// Different thresholds for different endpoints
public function test_homepage(): void
{
    $result = $this->loadTestUrl('/');
    $this->assertVTP95ResponseTime($result, 200); // Fast static page
}

public function test_complex_report(): void
{
    $result = $this->loadTestUrl('/reports/analytics');
    $this->assertVTP95ResponseTime($result, 2000); // Complex query
}
```

### 4. Test Critical Paths First

Focus on high-traffic and business-critical endpoints:

```php
public function test_critical_checkout_flow(): void
{
    // Checkout is critical - strict performance requirements
    $result = $this->runVoltTest($checkoutTest, [
        'virtual_users' => 30,
    ]);

    $this->assertVTSuccessful($result, 99.0);  // High success rate
    $this->assertVTP95ResponseTime($result, 500); // Fast response
}
```

### 5. Use CSV Data Sources

```php
public function test_user_authentication(): void
{
    $testClass = new class implements VoltTestCase {
        public function define(VoltTestManager $manager): void
        {
            $scenario = $manager->scenario('Auth Test')
                ->dataSource('test-users.csv', 'unique');

            $scenario->step('Login')
                ->post('/login', [
                    'email' => '${email}',
                    'password' => '${password}',
                ])
                ->expectStatus(200);
        }
    };

    $result = $this->runVoltTest($testClass);
    $this->assertVTSuccessful($result);
}
```

### 6. Separate Server Management per Test Class

```php
// Each test class can manage its own server
class FastApiTests extends IntegrationVoltTestCase
{
    protected static bool $enableServerManagement = true;
    protected static ?int $preferredPort = 8000;
}

class HeavyLoadTests extends IntegrationVoltTestCase
{
    protected static bool $enableServerManagement = true;
    protected static ?int $preferredPort = 8001; // Different port
}
```

### 7. Monitor and Document Baselines

```php
/**
 * Baseline performance test for homepage.
 *
 * Baseline metrics (as of 2025-01-15):
 * - P95: 180ms
 * - P99: 250ms
 * - Success rate: 99.5%
 */
public function test_homepage_baseline(): void
{
    $result = $this->loadTestUrl('/');

    $this->assertVTSuccessful($result, 99.0);
    $this->assertVTP95ResponseTime($result, 200);
    $this->assertVTP99ResponseTime($result, 300);
}
```

## Troubleshooting

### Server Won't Start

**Problem:** Server management fails to start the server.

**Solutions:**
1. Check ext-pcntl is installed: `php -m | grep pcntl`
2. Verify Laravel base path: Set `VOLTTEST_BASE_PATH` environment variable
3. Enable debug mode: `VOLTTEST_DEBUG_FOR_SERVER_MANAGEMENT=true`
4. Check port availability: Try a different port

```php
protected static ?int $preferredPort = 9000; // Use different port
```

### Test Fails with Connection Errors

**Problem:** Tests fail with "Connection refused" errors.

**Solutions:**
1. Enable server management:
   ```php
   protected static bool $enableServerManagement = true;
   ```
2. Check base URL matches server:
   ```php
   $this->setBaseUrl('http://localhost:8000');
   ```
3. Verify server is running:
   ```php
   $this->debugServer(); // Print server stats
   ```

### Assertions Fail Inconsistently

**Problem:** Performance assertions pass sometimes and fail other times.

**Solutions:**
1. Increase test duration for more stable results:
   ```php
   'duration' => '2m', // Longer duration = more stable metrics
   ```
2. Use appropriate thresholds:
   ```php
   $this->assertVTP95ResponseTime($result, 500); // Not too strict
   ```
3. Add ramp-up time:
   ```php
   'ramp_up' => '30s', // Gradual load increase
   ```

### Memory Issues with Large Tests

**Problem:** Tests fail with out-of-memory errors.

**Solutions:**
1. Reduce virtual users:
   ```php
   'virtual_users' => 10, // Start small
   ```
2. Disable report saving in tests:
   ```php
   config(['volttest.save_reports' => false]);
   ```
3. Increase PHP memory limit:
   ```bash
   php -d memory_limit=512M vendor/bin/phpunit
   ```

### CSV Data Not Found

**Problem:** Tests fail with "CSV file not found" errors.

**Solutions:**
1. Create CSV files in correct location:
   ```
   storage/volttest/data/users.csv
   ```
2. Use absolute paths:
   ```php
   ->dataSource(storage_path('volttest/data/users.csv'))
   ```
3. Check file permissions

### Parallel Test Conflicts

**Problem:** Multiple test classes interfere with each other.

**Solutions:**
1. Use different ports per test class:
   ```php
   // ClassA
   protected static ?int $preferredPort = 8000;

   // ClassB
   protected static ?int $preferredPort = 8001;
   ```
2. Run tests serially:
   ```bash
   ./vendor/bin/phpunit --do-not-cache-result
   ```

## Learn More

- [VoltTest PHP SDK Documentation](https://php.volt-test.com/docs)
- [CSV Data Sources Guide](CSV_DATA_SOURCE.md)
- [Main README](../README.md)
- [Performance Testing Best Practices](https://php.volt-test.com/best-practices)
