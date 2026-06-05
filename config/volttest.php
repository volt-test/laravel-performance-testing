<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VoltTest Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the VoltTest Laravel package.
    | Customize these settings to fit your application's performance testing needs.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Test Configuration
    |--------------------------------------------------------------------------
    */
    'name' => env('VOLTTEST_NAME', 'Laravel Application Test'),
    'description' => env('VOLTTEST_DESCRIPTION', 'Performance test for Laravel application'),

    /*
    |--------------------------------------------------------------------------
    | Load Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the load settings for your performance tests.
    | This includes the number of virtual users, test duration, and ramp-up time.
    | set to null to disable
    */
    'virtual_users' => env('VOLTTEST_VIRTUAL_USERS', 10),
    'duration' => env('VOLTTEST_DURATION'), // e.g., '1m', '30s', '2h'
    'ramp_up' => env('VOLTTEST_RAMP_UP', null), // e.g., '10s', '1m', '2h'

    /*
    |--------------------------------------------------------------------------
    | Stages Configuration
    |--------------------------------------------------------------------------
    |
    | Define stages for ramped load profiles. Each stage linearly ramps
    | from the previous target to the new target over the given duration.
    | When stages are set, virtual_users/duration/ramp_up are ignored.
    |
    | Example:
    |   'stages' => [
    |       ['duration' => '1m', 'target' => 50],
    |       ['duration' => '5m', 'target' => 100],
    |       ['duration' => '1m', 'target' => 0],
    |   ],
    |
    */
    'stages' => [],

    /*
    |--------------------------------------------------------------------------
    | Region Distribution
    |--------------------------------------------------------------------------
    |
    | Configure region distribution for cloud execution.
    | Weights must sum to 100. Leave empty for single-region default.
    |
    | Example:
    |   'regions' => [
    |       'us-east-1' => 60,
    |       'eu-west-1' => 40,
    |   ],
    |
    */
    'regions' => [],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    */
    'http_debug' => env('VOLTTEST_HTTP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Test Paths
    |--------------------------------------------------------------------------
    |
    | Define the paths where your VoltTest test cases are located.
    |
    */
    'test_paths' => app_path('VoltTests'),

    /*
    |--------------------------------------------------------------------------
    | Report Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how VoltTest generates and stores reports.
    |
    */
    'reports_path' => storage_path('volttest/reports'),

    /*
     * Enable or disable saving of reports
     * */
    'save_reports' => env('VOLTTEST_SAVE_REPORTS', true),

    /*
     * --------------------------------------------------------------------------
     * Base URL for the application under test
     * --------------------------------------------------------------------------
     * Configure the base URL for the application under test.
     * This is used to construct the full URLs for the test scenarios.
     *
     * */
    'use_base_url' => env('VOLTTEST_USE_BASE_URL', true),
    'base_url' => env('VOLTTEST_BASE_URL', 'http://localhost:8000'),

    /*
    --------------------------------------------------------------------------
     CSV Data Source Configuration
    --------------------------------------------------------------------------

     Configure CSV data sources for dynamic test data.
     This allows loading test data from CSV files for more realistic scenarios.

    */
    'csv_data' => [
        'path' => storage_path('volttest/data'), // Default CSV location
        'validate_files' => true,                // Check file exists before run
        'default_distribution' => 'unique',      // Default distribution mode
        'default_headers' => true,               // Default header setting
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloud Configuration
    |--------------------------------------------------------------------------
    |
    | Configure cloud execution for running tests on VoltTest Cloud.
    | Set VOLTTEST_API_KEY in your .env file to enable cloud execution.
    |
    */
    'cloud' => [
        'enabled' => env('VOLTTEST_CLOUD_ENABLED', false),
        'api_key' => env('VOLTTEST_API_KEY'),
    ],
];