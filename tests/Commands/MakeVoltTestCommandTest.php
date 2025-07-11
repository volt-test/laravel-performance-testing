<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Commands;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Mockery;
use Orchestra\Testbench\TestCase;
use VoltTest\Laravel\Commands\MakeVoltTestCommand;

class MakeVoltTestCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \VoltTest\Laravel\VoltTestServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('volttest.test_paths', '/tmp/volt-tests');
    }

    private function mockStubFile(): void
    {
        // Mock the stub file check in StubRenderer
        File::shouldReceive('exists')
            ->with(Mockery::pattern('/.*resources\/stubs\/volttest\.stub$/'))
            ->andReturn(false); // This will make it use the default stub
    }

    public function test_it_creates_basic_test_without_routes(): void
    {
        // Mock file operations
        $this->mockStubFile();

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests/UserTest.php')
            ->andReturn(false);

        File::shouldReceive('put')
            ->once()
            ->withArgs(function ($path, $content) {
                return $path === '/tmp/volt-tests/UserTest.php' &&
                    str_contains($content, 'class UserTest implements VoltTestCase') &&
                    str_contains($content, 'namespace App\\VoltTests;');
            });

        $this->artisan('volttest:make', ['name' => 'User'])
            ->expectsOutput('VoltTest performance test created: /tmp/volt-tests/UserTest.php')
            ->assertExitCode(0);
    }

    public function test_it_creates_test_with_test_suffix_when_not_provided(): void
    {
        $this->mockStubFile();

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests/UserTest.php')
            ->andReturn(false);

        File::shouldReceive('put')
            ->once()
            ->withArgs(function ($path, $content) {
                return $path === '/tmp/volt-tests/UserTest.php';
            });

        $this->artisan('volttest:make', ['name' => 'User'])
            ->assertExitCode(0);
    }

    public function test_it_preserves_test_suffix_when_already_present(): void
    {
        $this->mockStubFile();

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests/UserTest.php')
            ->andReturn(false);

        File::shouldReceive('put')
            ->once()
            ->withArgs(function ($path, $content) {
                return $path === '/tmp/volt-tests/UserTest.php';
            });

        $this->artisan('volttest:make', ['name' => 'UserTest'])
            ->assertExitCode(0);
    }

    public function test_it_creates_directory_if_it_does_not_exist(): void
    {
        $this->mockStubFile();

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests')
            ->andReturn(false);

        File::shouldReceive('makeDirectory')
            ->with('/tmp/volt-tests', 0755, true)
            ->once();

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests/UserTest.php')
            ->andReturn(false);

        File::shouldReceive('put')
            ->once();

        $this->artisan('volttest:make', ['name' => 'User'])
            ->assertExitCode(0);
    }

    public function test_it_handles_empty_test_name(): void
    {
        $this->artisan('volttest:make', ['name' => ''])
            ->expectsOutput('The test name must be a non-empty string.')
            ->assertExitCode(0);
    }

    public function test_it_creates_test_with_routes_discovery(): void
    {
        // Set up some routes for discovery
        $router = $this->app->make(Router::class);
        $router->get('/users', fn () => 'users')->name('users.index');
        $router->post('/users', fn () => 'create user')->name('users.store');
        $router->put('/users/{id}', fn ($id) => "update user $id")->name('users.update');
        $router->patch('/users/{id}', fn ($id) => "patch user $id")->name('users.patch');
        $router->get('/products', fn () => 'products')->name('products.index');
        $router->post('/products', fn () => 'create product')->name('products.store');
        $router->put('/products/{id}', fn ($id) => "update product $id")->name('products.update');
        $router->patch('/products/{id}', fn ($id) => "patch product $id")->name('products.patch');
        // API routes
        $router->get('/api/users', fn () => response()->json(['users']))->name('api.users.index');
        $router->post('/api/users', fn () => response()->json(['created' => true]))->name('api.users.store');
        $router->put('/api/users/{id}', fn ($id) => response()->json(['updated' => $id]))->name('api.users.update');
        $router->patch('/api/users/{id}', fn ($id) => response()->json(['patched' => $id]))->name('api.users.patch');
        $router->get('/api/products', fn () => response()->json(['products']))->name('api.products.index');
        $router->post('/api/products', fn () => response()->json(['created' => true]))->name('api.products.store');
        $router->put('/api/products/{id}', fn ($id) => response()->json(['updated' => $id]))->name('api.products.update');
        $router->patch('/api/products/{id}', fn ($id) => response()->json(['patched' => $id]))->name('api.products.patch');
        $this->mockStubFile();

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests/UserTest.php')
            ->andReturn(false);

        File::shouldReceive('put')
            ->once()
            ->withArgs(function ($path, $content) {
                return $path === '/tmp/volt-tests/UserTest.php'
                    && str_contains($content, 'VoltTestCase')
                    && str_contains($content, "->step('Users.index')")
                    && str_contains($content, "->step('Api.products.patch')");
            });

        $this->artisan('volttest:make', [
            'name' => 'User',
            '--routes' => true,
        ])
            ->assertExitCode(0);
    }

    public function test_command_signature_is_correct(): void
    {
        $command = new MakeVoltTestCommand($this->app->make(Router::class));

        $this->assertEquals('volttest:make', $command->getName());
        $this->assertStringContainsString('Create a new VoltTest performance test', $command->getDescription());
    }

    public function test_it_includes_select_option_in_signature(): void
    {
        $command = new MakeVoltTestCommand($this->app->make(Router::class));
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('select'));
        $this->assertTrue($definition->hasOption('routes'));
        $this->assertTrue($definition->hasOption('filter'));
        $this->assertTrue($definition->hasOption('method'));
        $this->assertTrue($definition->hasOption('auth'));
    }

    public function test_it_creates_test_with_routes_discovery_and_filter_api(): void
    {
        // Set up some routes for discovery
        $router = $this->app->make(Router::class);
        $router->get('/users', fn () => 'users')->name('users.index');
        $router->post('/users', fn () => 'create user')->name('users.store');
        $router->get('/api/users', fn () => response()->json(['users']))->name('api.users.index');
        $router->post('/api/users', fn () => response()->json(['created' => true]))->name('api.users.store');

        $this->mockStubFile();

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with('/tmp/volt-tests/ApiUserTest.php')
            ->andReturn(false);

        File::shouldReceive('put')
            ->once()
            ->withArgs(function ($path, $content) {
                return $path === '/tmp/volt-tests/ApiUserTest.php' &&
                    str_contains($content, 'Api.users.index') &&
                    str_contains($content, "->get('/api/users', ['Authorization' => 'Bearer \${token}', 'Content-Type' => 'application/json', 'Accept' => 'application/json'])");
            });

        $this->artisan('volttest:make', [
            'name' => 'ApiUser',
            '--routes' => true,
            '--filter' => 'api*',
        ])
            ->assertExitCode(0);
    }
}
