<?php

declare(strict_types=1);

namespace VoltTest\Laravel;

use Illuminate\Support\ServiceProvider;
use VoltTest\Laravel\Commands\MakeVoltTestCommand;
use VoltTest\Laravel\Commands\RunVoltTestCommand;

class VoltTestServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish the configuration file
            $this->publishConfig();

            // Register the Artisan commands
            $this->registerCommands();
        }

        // Load the Views
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/volttest.php', 'volttest');
        // Register the service provider
        $this->app->singleton('laravel-volttest', function () {
            return new VoltTestManager(config('volttest'));
        });
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/volttest.php' => config_path('volttest.php'),
        ], 'volttest-config');

        $this->publishes([
            __DIR__ . '/../resources/views/' => resource_path('views/vendor/volttest'),
        ], 'volttest-views');
    }

    /*
     * Register the Artisan commands
     * @return void
     * */
    protected function registerCommands(): void
    {
        $this->commands([
            MakeVoltTestCommand::class,
            RunVoltTestCommand::class,
        ]);
    }
}
