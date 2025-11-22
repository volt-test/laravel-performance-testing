# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.2.0] - 2025-11-22

### Added

#### PHPUnit Integration
- **PHPUnit Integration Support** - Run VoltTest performance tests within PHPUnit test suites
- **PerformanceTestCase Base Class** - Abstract base class for creating PHPUnit performance tests with built-in server management and VoltTest integration (formerly `IntegrationVoltTestCase`)
- **Reusable VoltTest Classes** - Run existing `VoltTestCase` classes (from `app/VoltTests/`) in PHPUnit tests, enabling code reuse between Artisan commands and PHPUnit test suites
- **VoltTestAssertions Trait** - Comprehensive assertion methods for validating performance test results including:
  - Success rate assertions (`assertVTSuccessful`, `assertVTErrorRate`)
  - Response time assertions (min, max, average, median, P95, P99)
  - Throughput assertions (minimum requests, RPS limits)
- **Quick Load Testing Helpers** - Convenience methods `loadTestUrl()` and `loadTestApi()` for simple performance testing scenarios

#### Server Management
- **ServerManager Class** - Manages PHP development server lifecycle with automatic port selection and health checks
- **ServerRegistry Class** - Global registry for managing multiple server instances across test suites with automatic cleanup
- **Automatic Server Lifecycle** - Start and stop development servers automatically before and after test classes
- **Port Conflict Resolution** - Automatic detection and handling of port conflicts with fallback port selection

#### Testing Infrastructure
- **ServerManagerTest** - Comprehensive test coverage for server management functionality
- **ServerRegistryTest** - Test coverage for server registry and multi-server scenarios
- **Performance Test Examples** - Added example tests demonstrating PHPUnit integration patterns

#### Documentation
- **PHPUnit Integration Guide** - Comprehensive documentation at `docs/PHPUNIT_INTEGRATION.md` covering:
  - Complete setup instructions and requirements
  - All available assertions with examples
  - Server management configuration
  - Best practices and troubleshooting
  - Real-world usage examples
- **CHANGELOG.md** - Added this changelog file to track version history and changes

#### Dependencies
- **ext-pcntl requirement** - Added to composer.json for process control functionality needed by server management

### Changed
- **Class Rename** - Renamed `IntegrationVoltTestCase` to `PerformanceTestCase` for clearer, more concise naming that better describes its purpose
- **CI Configuration** - Updated GitHub Actions workflow to trigger on all branches for comprehensive testing
- **PHPUnit Configuration** - Refactored phpunit.xml to:
  - Support PHPUnit 11.0 compatibility
  - Enhance test reporting with display settings
  - Improve deprecation and error handling
  - Add performance test suite configuration
- **Type Safety** - Added strict types declaration to ServerManagerTest for improved type safety and error detection
- **Test Annotations** - Removed deprecated `@test` annotations in favor of method name convention (`test_*`)

### Fixed
- **Test Annotations** - Remove deprecated `@test` annotation from RunVoltTestCommandTest and TestConfigurationValidatorTest to comply with PHPUnit 11 best practices
- **Assertion Logic** - Fixed `assertVTMinResponseTime` to correctly validate minimum response time thresholds
- **Time Parsing** - Improved time format parsing in `parseTimeToMs` to handle various time units (hours, minutes, seconds, milliseconds, microseconds, nanoseconds)

## [1.1.0] - 2024-07-11

### Changed
- Release/add content type by @elwafa in #9
- Release(v1.1.0) by @elwafa in #10
- Docs: Update Headers usage by @elwafa in #12

### Full Changelog
[v1.0.0...v1.1.0](https://github.com/volt-test/laravel-performance-testing/compare/v1.0.0...v1.1.0)

## [1.0.0] - 2024-06-26

### Added
- Initial stable release
- Core VoltTest functionality
- Artisan commands: `volttest:make` and `volttest:run`
- Route discovery features
- Service provider integration

## [0.0.6-beta] - 2024-06-26

### Added
- Merge pull request #8 from volt-test/laravel-versions-with-stability

## [0.0.5-beta] - 2024-06-26

### Fixed
- Slimmer versions with stability

## [0.0.4-beta] - 2024-06-26

### Fixed
- Updated: No base No beta

## [0.0.3-beta] - 2024-06-24

### Added
- New beta release

## [0.0.2-beta] - 2024-06-23

### Added
- Second beta

## [0.0.1-beta] - 2024-06-23

### Added
- First beta release

## Links

- [Unreleased]: https://github.com/volt-test/laravel-performance-testing/compare/v1.1.0...HEAD
- [1.1.0]: https://github.com/volt-test/laravel-performance-testing/compare/v1.0.0...v1.1.0
- [1.0.0]: https://github.com/volt-test/laravel-performance-testing/compare/0.0.6-beta...v1.0.0
- [0.0.6-beta]: https://github.com/volt-test/laravel-performance-testing/compare/0.0.5-beta...0.0.6-beta
- [0.0.5-beta]: https://github.com/volt-test/laravel-performance-testing/compare/0.0.4-beta...0.0.5-beta
- [0.0.4-beta]: https://github.com/volt-test/laravel-performance-testing/compare/0.0.3-beta...0.0.4-beta
- [0.0.3-beta]: https://github.com/volt-test/laravel-performance-testing/compare/0.0.2-beta...0.0.3-beta
- [0.0.2-beta]: https://github.com/volt-test/laravel-performance-testing/compare/0.0.1-beta...0.0.2-beta
- [0.0.1-beta]: https://github.com/volt-test/laravel-performance-testing/releases/tag/0.0.1-beta