# Laravel Performance Testing

A Laravel package for performance testing with the VoltTest PHP SDK. Easily create and run load tests for your Laravel applications with built-in route discovery, CSRF handling, and comprehensive reporting.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/volt-test/laravel-performance-testing.svg?style=flat-square)](https://packagist.org/packages/volt-test/laravel-performance-testing) [![Total Downloads](https://img.shields.io/packagist/dt/volt-test/laravel-performance-testing.svg?style=flat-square)](https://packagist.org/packages/volt-test/laravel-performance-testing) [![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/volt-test/laravel-performance-testing/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/volt-test/laravel-performance-testing/actions)

## Features

- **Artisan Commands** — Create and run tests from the CLI
- **Route Discovery** — Auto-generate test scaffolds from your Laravel routes
- **Target Configuration** — Set the target URL explicitly or auto-detect from config
- **Stages** — Define ramped load profiles (ramp-up, hold, spike, ramp-down)
- **Cloud Execution** — Run tests on VoltTest Cloud with multi-region support
- **CSV Data Sources** — Drive tests with dynamic data from CSV files
- **PHPUnit Integration** — Run performance tests in your test suite with assertions
- **CSRF & Cookie Handling** — Automatic Laravel session and CSRF token management

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- VoltTest PHP SDK ^1.2

## Installation

```bash
composer require volt-test/laravel-performance-testing --dev
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=volttest-config
```

## Quick Start

### 1. Create a test

```bash
php artisan volttest:make LoginTest
```

### 2. Define your scenario

```php
class LoginTest implements VoltTestCase
{
    public function define(VoltTestManager $manager): void
    {
        $manager->target('http://localhost:8000');

        $scenario = $manager->scenario('Login Flow');

        $scenario->step('Get Login Page')
            ->get('/login')
            ->expectStatus(200)
            ->extractCsrfToken();

        $scenario->step('Submit Login')
            ->post('/login', [
                '_token' => '${csrf_token}',
                'email' => 'user@example.com',
                'password' => 'password',
            ])
            ->expectStatus(302);
    }
}
```

### 3. Run the test

```bash
php artisan volttest:run LoginTest --users=10 --duration=30s
```

### 4. Direct URL testing

```bash
php artisan volttest:run https://api.example.com/health --url --users=50 --duration=1m
```

## Documentation

For full documentation, visit **[docs.volt-test.com](https://docs.volt-test.com)**:

| Topic | Description |
|-------|-------------|
| [Installation](https://docs.volt-test.com/docs/laravel/laravel-installation) | Setup and configuration |
| [Quick Start](https://docs.volt-test.com/docs/laravel/laravel-quick-start) | Get running in 5 minutes |
| [Artisan Commands](https://docs.volt-test.com/docs/laravel/laravel-cli-commands) | `volttest:make` and `volttest:run` options |
| [Creating Tests](https://docs.volt-test.com/docs/laravel/laravel-creating-tests) | Test structure, scenarios, steps, and route discovery |
| [API Testing](https://docs.volt-test.com/docs/laravel/laravel-api-testing) | Authentication flows, CRUD, and data extraction |
| [Web Testing](https://docs.volt-test.com/docs/laravel/laravel-web-testing) | HTML forms, CSRF tokens, and multi-step flows |
| [Data-Driven Testing](https://docs.volt-test.com/docs/laravel/laravel-data-driven-testing) | CSV data sources and distribution modes |
| [PHPUnit Integration](https://docs.volt-test.com/docs/laravel/laravel-phpunit-integration) | Performance assertions and server management |
| [Assertions](https://docs.volt-test.com/docs/laravel/laravel-assertions) | Available performance assertions |
| [Cloud Execution](https://docs.volt-test.com/docs/laravel/laravel-cloud-mode) | Run on VoltTest Cloud with multi-region support |
| [Configuration](https://docs.volt-test.com/docs/laravel/laravel-configuration) | Full config reference |

For core VoltTest PHP SDK documentation, visit **[docs.volt-test.com](https://docs.volt-test.com)**.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.
