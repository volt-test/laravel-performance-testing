# Laravel Performance Testing

A Laravel package for performance testing with the VoltTest PHP SDK. Easily create and run load tests for your Laravel applications with built-in route discovery, CSRF handling, and comprehensive reporting.

This package is built on top of the **[VoltTest PHP SDK](https://php.volt-test.com/)** and provides a seamless Laravel integration layer with additional Laravel-specific features like automatic route discovery, CSRF token handling, and Artisan commands.

For more information about the core VoltTest functionality, visit **[php.volt-test.com](https://php.volt-test.com/)**.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/volt-test/laravel-performance-testing.svg?style=flat-square)](https://packagist.org/packages/volt-test/laravel-performance-testing) [![Total Downloads](https://img.shields.io/packagist/dt/volt-test/laravel-performance-testing.svg?style=flat-square)](https://packagist.org/packages/volt-test/laravel-performance-testing) [![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/volt-test/laravel-performance-testing/run-tests?label=tests)](https://github.com/volt-test/laravel-performance-testing/actions?query=workflow%3Arun-tests+branch%3Amain)

## Table of Contents

- [About Laravel Performance Testing VoltTest](#about-laravel-performance-testing-volttest)
- [Requirements](#requirements)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Creating Tests](#creating-tests)
- [API Testing](#api-testing)
- [CSV Data Sources](#csv-data-sources)
- [Available Methods](#available-methods)
- [Running Tests](#running-tests)
- [Reports](#reports)
- [Advanced Configuration](#advanced-configuration)
- [Testing Tips](#testing-tips)
- [Troubleshooting](#troubleshooting)
- [Learn More](#learn-more)

## About Laravel Performance Testing VoltTest

This Laravel package extends the **[VoltTest PHP SDK](https://php.volt-test.com/)** with Laravel-specific functionality. While the core VoltTest PHP SDK provides the foundation for performance testing, this package adds:

- **Laravel Integration** - Service provider, facades, and configuration
- **Artisan Commands** - CLI commands for test creation and execution
- **Route Discovery** - Automatic Laravel route detection and test generation
- **Laravel Scenarios** - Enhanced scenario class with Laravel-specific methods

For comprehensive documentation about VoltTest core features, load testing concepts, and advanced configuration options, please visit **[php.volt-test.com](https://php.volt-test.com/)**.

## Requirements

- PHP 8.2+
- VoltTest PHP SDK 1.1.0+

## Features

- 🚀 **Easy Laravel Integration** - Seamlessly integrates with Laravel applications
- 🔍 **Automatic Route Discovery** - Discover and test your application routes automatically
- 📊 **Comprehensive Reporting** - Detailed performance reports with metrics
- 🎯 **URL Load Testing** - Direct URL testing without creating test classes
- ⚡ **Artisan Commands** - Convenient CLI commands for test creation and execution
- 🔧 **Configurable** - Flexible configuration options for different environments
- 📄 **CSV Data Sources** - Load dynamic test data from CSV files for realistic performance testing

## Installation

You can install the package via Composer:

```bash
composer require volt-test/laravel-performance-testing --dev
```

The package will automatically register its service provider.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=volttest-config
```

## Configuration

The configuration file `config/volttest.php` contains all the settings for your performance tests:

```php
return [
    // Test Configuration
    'name' => env('VOLTTEST_NAME', 'Laravel Application Test'),
    'description' => env('VOLTTEST_DESCRIPTION', 'Performance test for Laravel application'),

    // Load Configuration
    'virtual_users' => env('VOLTTEST_VIRTUAL_USERS', 10),
    'duration' => env('VOLTTEST_DURATION'), // e.g., '1m', '30s', '2h'
    'ramp_up' => env('VOLTTEST_RAMP_UP', null),

    // Debug Configuration
    'http_debug' => env('VOLTTEST_HTTP_DEBUG', false),

    // Test Paths
    'test_paths' => app_path('VoltTests'),

    // Reports
    'reports_path' => storage_path('volttest/reports'),
    'save_reports' => env('VOLTTEST_SAVE_REPORTS', true),

    // Base URL
    'use_base_url' => env('VOLTTEST_USE_BASE_URL', true),
    'base_url' => env('VOLTTEST_BASE_URL', 'http://localhost:8000'),

    // CSV Data Source Configuration
    'csv_data' => [
        'path' => storage_path('volttest/data'), // Default CSV location
        'validate_files' => true,                // Check file exists before run
        'default_distribution' => 'unique',      // Default distribution mode
        'default_headers' => true,               // Default header setting
    ],
];
```

## Quick Start

### 1. Create Your First Test

Generate a new performance test with route discovery:

```bash
php artisan volttest:make UserTest --routes --select
```

This creates a test class at `app/VoltTests/UserTest.php`:

```php
<?php

namespace App\VoltTests;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class UserTest implements VoltTestCase
{
    public function define(VoltTestManager $manager): void
    {
        $scenario = $manager->scenario('UserTest');

        // Step 1: Home Page
        $scenario->step('Home Page')
            ->get('/')
            ->expectStatus(200);
            
        // Step 2: Get Registration Page to extract CSRF token
        $scenario->step('Get Registration Page')
            ->get('/register')
            ->expectStatus(200)
            ->extractCsrfToken('csrf_token'); // Extract CSRF token for registration

        // Step 2: User Registration
        $scenario->step('User Registration')
            ->post('/register', [
                '_token' => '${csrf_token}',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(201);
    }
}
```

### 2. Run Your Test

Execute the performance test:

```bash
php artisan volttest:run UserTest
```

Or run all tests:

```bash
php artisan volttest:run
```

### 3. Direct URL Testing

Test any URL directly without creating a test class:

```bash
# Simple GET request
php artisan volttest:run https://example.com --users=10 --duration=1m

# POST request with data
php artisan volttest:run https://api.example.com/users \
    --method=POST \
    --headers='{"Authorization":"Bearer token"}' \
    --body='{"name":"John","email":"john@example.com"}' \
    --content-type="application/json" \
    --code-status=201
```

## Creating Tests

### Using Route Discovery

Generate tests with automatic route discovery:

```bash
# Include all routes
php artisan volttest:make ApiTest --routes

# Filter by pattern
php artisan volttest:make ApiTest --routes --filter="api/*"

# Filter by HTTP method
php artisan volttest:make ApiTest --routes --method=GET

# Include only authenticated routes
php artisan volttest:make ApiTest --routes --auth

# Interactive route selection
php artisan volttest:make ApiTest --routes --select --filter="api/*"
```

### Manual Test Creation

Create a test class manually:

```php
<?php

namespace App\VoltTests;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class CheckoutTest implements VoltTestCase
{
    public function define(VoltTestManager $manager): void
    {
        $scenario = $manager->scenario('E-commerce Checkout Flow');

        // Browse products
        $scenario->step('Browse Products')
            ->get('/products')
            ->expectStatus(200)
            ->extractHtml('product_id', '.product:first-child', 'data-id');
        // To know how to extract the product ID, you can use a CSS selector that matches the first product element.
        // Reference: https://php.volt-test.com/docs/Steps#html-response

        // Add to cart
        $scenario->step('Add to Cart')
            ->post('/cart/add', [
                '_token' => '${csrf_token}',
                'product_id' => '${product_id}',
                'quantity' => 1,
            ])
            ->expectStatus(200)
            ->thinkTime('2s');

        // Checkout
        $scenario->step('Checkout')
            ->get('/checkout')
            ->expectStatus(200)
            ->extractCsrfToken('checkout_token');

        // Complete order
        $scenario->step('Complete Order')
            ->post('/checkout/complete', [
                '_token' => '${checkout_token}',
                'payment_method' => 'credit_card',
                'shipping_address' => 'Test Address',
            ])
            ->expectStatus(302); // Redirect after successful order
    }
}
```

## API Testing

Create API-focused tests:

```php
<?php

namespace App\VoltTests;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class ApiTest implements VoltTestCase
{
    public function define(VoltTestManager $manager): void
    {
        $scenario = $manager->scenario('API Performance Test');

        // Login to get token
        $scenario->step('API Login')
            ->post('/api/login', [
                'email' => 'test@example.com',
                'password' => 'password',
            ])
            ->header('Accept', 'application/json')
            ->expectStatus(200)
            ->extractJson('auth_token', 'meta.token');
        // Extract the authentication token from the response
        // Reference: https://php.volt-test.com/docs/Steps#json-response

        // Get user data
        $scenario->step('Get User Data')
            ->get('/api/user')
            ->header('Authorization', 'Bearer ${auth_token}')
            ->header('Accept', 'application/json')
            ->expectStatus(200)
            ->extractJson('user_id', 'data.id');

        // Update user
        $scenario->step('Update User')
            ->put('/api/user/${user_id}', [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->header('Authorization', 'Bearer ${auth_token}')
            ->header('Content-Type', 'application/json')
            ->expectStatus(200);
    }
}
```

## Available Methods

### Scenario Methods

```php
// HTTP Methods
$scenario->step('Step Name')
    ->get('/path')
    ->post('/path', $data)
    ->put('/path', $data)
    ->patch('/path', $data)
    ->delete('/path');
```
### Headers Data

```php

// Headers
$scenario->step('Step Name')
    ->header('Authorization', 'Bearer token')
    ->header('Accept', 'application/json');
    
```

### Assertions

```php

// Expectations
$scenario->step('Step Name')
    ->expectStatus(200)
    ->expectStatus(201, 'custom_validation_name');
```

### Extracting Data

```php

// Data Extraction
$scenario->step('Step Name')
    ->extractJson('variable_name', 'path.to.value') // Reference: https://php.volt-test.com/docs/Steps#json-response
    ->extractHeader('variable_name', 'Header-Name') // Reference: https://php.volt-test.com/docs/Steps#header-response
    ->extractHtml('variable_name', 'css-selector', 'attribute') // Reference: https://php.volt-test.com/docs/Steps#html-response
    ->extractRegex('variable_name', '/pattern/') // Reference: https://php.volt-test.com/docs/Steps#regular-expressions
    ->extractCsrfToken('csrf_token'); // Laravel-specific CSRF token extraction or u can use `extractHtml` with the CSRF token input field

// Think Time
$scenario->step('Step Name')
    ->thinkTime('2s'); // Pause between requests
```

## Running Tests

### Command Syntax

```bash
php artisan volttest:run [test] [options]
```

### Arguments

- `test` (optional) - The test class to run OR URL to test

### Available Options

|Option|Description|Example|
|---|---|---|
|`--path=`|Path to search for test classes|`--path=tests/Performance`|
|`--debug`|Enable HTTP debugging|`--debug`|
|`--users=`|Number of virtual users (default: 10)|`--users=50`|
|`--duration=`|Test duration (optional)|`--duration=2m`|
|`--stream`|Stream test output to console|`--stream`|
|`--url`|Treat the test argument as a URL for direct load testing|`--url`|
|`--method=`|HTTP method for URL testing (default: GET)|`--method=POST`|
|`--headers=`|JSON string of headers for URL testing|`--headers='{"Authorization":"Bearer token"}'`|
|`--body=`|Request body for URL testing (for POST/PUT)|`--body='{"name":"John"}'`|
|`--content-type=`|Content type for URL testing|`--content-type=application/json`|
|`--code-status=`|Expected HTTP status code for URL testing (default: 200)|`--code-status=201`|
|`--scenario-name=`|Custom scenario name for URL testing|`--scenario-name="API Load Test"`|

### Basic Execution

```bash
# Run all tests
php artisan volttest:run

# Run specific test
php artisan volttest:run UserTest

# Run with custom configuration
php artisan volttest:run --users=20 --duration=2m --debug

# Stream output in real-time
php artisan volttest:run --stream
```

### Duration Formats

The `--duration` option accepts various time formats:

- `30s` - 30 seconds
- `5m` - 5 minutes
- `2h` - 2 hours
- `90s` - 90 seconds (1.5 minutes)

### Header Formats

The `--headers` option accepts JSON format:

```bash
# Single header
--headers='{"Authorization":"Bearer token"}'

# Multiple headers
--headers='{"Authorization":"Bearer token","Accept":"application/json","X-Custom":"value"}'

# Complex headers with special characters
--headers='{"User-Agent":"VoltTest/1.0","Content-Type":"application/json; charset=utf-8"}'
```

### Body Formats

The `--body` option supports different formats depending on content type:

```bash
# JSON body (use with --content-type=application/json)
--body='{"name":"John","email":"john@example.com","age":30}'

# Form data (use with --content-type=application/x-www-form-urlencoded)  
--body="name=John&email=john@example.com&age=30"

# Plain text
--body="This is plain text content"

# XML (use with --content-type=application/xml)
--body='<?xml version="1.0"?><user><name>John</name><email>john@example.com</email></user>'
```

### Advanced Options

```bash
# Custom test path
php artisan volttest:run --path=tests/Performance

# Multiple configuration options
php artisan volttest:run UserTest \
    --users=50 \
    --duration=5m \
    --debug \
    --stream
```

## Reports

### Console Output

Test results are displayed in the console with metrics including:

- Duration and total requests
- Success rate and requests per second
- Response time statistics (min, max, avg, median, P95, P99)

### Saved Reports

Reports are automatically saved as JSON files in `storage/volttest/reports/`:

```json
{
  "timestamp": "2025-06-23 21:22:39",
  "metadata": {
    "generator": "VoltTest Laravel Package"
  },
  "summary": {
    "duration": "5.521942656s",
    "total_requests": 100,
    "success_rate": 48,
    "requests_per_second": 18.11,
    "success_requests": 48,
    "failed_requests": 52
  },
  "response_times": {
    "min": "22.361918ms",
    "max": "2.061161165s",
    "avg": "343.184136ms",
    "median": "106.130464ms",
    "p95": "1.592337048s",
    "p99": "1.84969821s"
  },
  "metrics": {
    "duration": "5.521942656s",
    "totalRequests": 100,
    "successRate": 48,
    "requestsPerSecond": 18.11,
    "successRequests": 48,
    "failedRequests": 52,
    "responseTime": {
      "min": "22.361918ms",
      "max": "2.061161165s",
      "avg": "343.184136ms",
      "median": "106.130464ms",
      "p95": "1.592337048s",
      "p99": "1.84969821s"
    }
  }
}
```

## CSV Data Sources

Load dynamic test data from CSV files for more realistic and scalable performance testing scenarios.

### Quick Example

1. **Create a CSV file** at `storage/volttest/data/users.csv`:

```csv
email,password,user_id,name
user1@example.com,password123,1,John Doe
user2@example.com,password456,2,Jane Smith
user3@example.com,password789,3,Bob Wilson
```

2. **Use the data in your test**:

```php
<?php

namespace App\VoltTests;

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class UserAuthTest implements VoltTestCase
{
    public function define(VoltTestManager $manager): void
    {
        // Configure CSV data source
        $scenario = $manager->scenario('User Login Flow')
            ->dataSource('users.csv', 'unique', true);

        $scenario->step('Login')
            ->post('/login', [
                'email' => '${email}',        // From CSV column
                'password' => '${password}'   // From CSV column
            ])
            ->expectStatus(200);

        $scenario->step('View Profile')
            ->get('/profile/${user_id}')     // user_id from same CSV row
            ->expectStatus(200);
    }
}
```

### Distribution Modes

- **`unique`**: Each virtual user gets a different CSV row (recommended for user authentication)
- **`random`**: Each virtual user gets a random CSV row (good for product browsing)
- **`sequential`**: Virtual users cycle through CSV rows in order (predictable patterns)

### E-commerce Example

```php
// CSV file: storage/volttest/data/products.csv
// product_id,sku,name,price,category
// 1,SKU001,Laptop,999.99,electronics
// 2,SKU002,Mouse,29.99,accessories

$scenario = $manager->scenario('Product Browsing')
    ->dataSource('products.csv', 'random', true);

$scenario->step('View Product')
    ->get('/products/${product_id}')
    ->expectStatus(200);

$scenario->step('Add to Cart')
    ->post('/cart/add', [
        'product_id' => '${product_id}',
        'quantity' => '1'
    ])
    ->expectStatus(200);
```

### Advanced CSV Usage

```php
// Multiple scenarios with different data sources
$userScenario = $manager->scenario('User Actions')
    ->dataSource('users.csv', 'unique')   // Each user gets different account
    ->weight(70);                         // 70% of traffic

$adminScenario = $manager->scenario('Admin Actions')
    ->dataSource('admins.csv', 'random') // Admins can overlap
    ->weight(30);                        // 30% of traffic
```

### 📖 Complete CSV Documentation

For detailed information including:
- File format requirements and validation
- All distribution modes with use cases
- Configuration options and file paths
- Troubleshooting and performance tips
- Advanced patterns and best practices

**[Read the complete CSV Data Source guide →](docs/CSV_DATA_SOURCE.md)**


## Advanced Configuration

### Custom Test Paths

```php
// config/volttest.php
'test_paths' => [
    app_path('VoltTests'),
    base_path('tests/Performance'),
    base_path('custom/performance/tests'),
],
```

### Custom Reports Path

```php
// config/volttest.php
'reports_path' => storage_path('app/performance-reports'),
'save_reports' => true,
```

## Testing Tips

### 1. Use Think Time

Add realistic delays between requests:

```php
$scenario->step('Browse Products')
    ->get('/products')
    ->thinkTime('3s'); // User reading time
```

### 2. Extract Dynamic Values

Handle dynamic content properly:

```php
$scenario->step('Login')
    ->get('/login')
    ->extractCsrfToken()
    ->post('/login', [
        '_token' => '${csrf_token}',
        'email' => 'user@example.com',
        'password' => 'password',
    ]);
```

### 3. Test User Journeys

Create realistic user flows:

```php
// Complete user journey
$scenario->step('Homepage')->get('/');
$scenario->step('Browse Products')->get('/products');
$scenario->step('View Product')->get('/products/1');
$scenario->step('Add to Cart')->post('/cart/add', $data);
$scenario->step('Checkout')->get('/checkout');
$scenario->step('Complete Order')->post('/orders', $orderData);
```

### 4. Handle Authentication

For API testing with authentication:

```php
$scenario->step('Login')
    ->post('/api/login', $credentials)
    ->extractJson('token', 'access_token');

$scenario->step('Protected Resource')
    ->get('/api/protected')
    ->header('Authorization', 'Bearer ${token}');
```

## Troubleshooting

### Common Issues

**Route Discovery Not Working**

- Make sure routes are properly registered
- Check that middleware is correctly applied
- Use `--select` option to manually choose routes

**Base URL Problems**

- Set `VOLTTEST_BASE_URL` to your application URL
- Ensure your application is running during tests
- For Docker setups, use the correct internal URL

### Debug Mode

Enable debugging to see HTTP request/response details:

```bash
php artisan volttest:run --debug --stream
```

Or in configuration:

```php
'http_debug' => true,
```

## Testing

Run the package tests:

```bash
composer test
```

## Learn More

- **[Laravel tutorial for php sdk](https://php.volt-test.com/blog/stress-testing-laravel-with-volt-test-web-ui)** - Step-by-step guide to using VoltTest with Laravel
- **[VoltTest PHP SDK Documentation](https://php.volt-test.com/)** - Complete guide to VoltTest core features
- **[Performance Testing types](https://php.volt-test.com/blog/performance-testing-types)** - Load testing concepts and types
- **[VoltTest API Reference](https://php.volt-test.com/docs/introduction)** - Full API documentation

## Credits

- [Islam ElWafa](https://github.com/elwafa)
- [VoltTest PHP SDK](https://php.volt-test.com/)
- [All Contributors](https://github.com/volt-test/laravel-performance-testing/graphs/contributors)