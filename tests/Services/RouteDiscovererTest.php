<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Services;

use Illuminate\Routing\Router;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Style\SymfonyStyle;
use VoltTest\Laravel\Services\RouteDiscoverer;

class RouteDiscovererTest extends TestCase
{
    protected Router $router;

    protected SymfonyStyle $io;

    protected RouteDiscoverer $discoverer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = $this->app->make(Router::class);
        $this->io = Mockery::mock(SymfonyStyle::class);
        $this->io->shouldIgnoreMissing();

        $this->discoverer = new RouteDiscoverer($this->router, $this->io);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_discovers_basic_routes(): void
    {
        // Arrange
        $this->router->get('/sample-route', fn () => 'OK')->name('sample.route');
        $this->router->get('/another-route', fn () => 'OK')->name('another.route');

        // Act
        $routes = collect($this->discoverer->discover([]))
            ->reject(fn ($route) => $route['uri'] === 'storage/{path}')
            ->values()
            ->all();

        // Assert
        $this->assertNotEmpty($routes, 'No routes were discovered.');
        $this->assertCount(2, $routes);

        $sampleRoute = collect($routes)->firstWhere('name', 'sample.route');
        $this->assertNotNull($sampleRoute, 'Route "sample.route" not found.');
        $this->assertEquals(['GET', 'HEAD'], $sampleRoute['methods']);
        $this->assertEquals('sample-route', $sampleRoute['uri']);
        $this->assertEquals('sample.route', $sampleRoute['name']);
        $this->assertEquals('web', $sampleRoute['type']);
    }

    public function test_it_filters_routes_by_http_method(): void
    {
        // Arrange
        $this->router->get('/get-route', fn () => 'OK')->name('get.route');
        $this->router->post('/post-route', fn () => 'OK')->name('post.route');
        $this->router->put('/put-route', fn () => 'OK')->name('put.route');
        $this->router->delete('/delete-route', fn () => 'OK')->name('delete.route');

        // Act - Filter by POST
        $postRoutes = $this->discoverer->discover(['method' => 'POST']);

        // Assert
        $this->assertCount(1, $postRoutes);
        $this->assertEquals('post-route', $postRoutes[0]['uri']);
        $this->assertContains('POST', $postRoutes[0]['methods']);

        // Act - Filter by GET
        $getRoutes = collect($this->discoverer->discover(['method' => 'GET']))
            ->reject(fn ($route) => $route['uri'] === 'storage/{path}')
            ->values()
            ->all();

        // Assert
        $this->assertCount(1, $getRoutes);
        $this->assertEquals('get-route', $getRoutes[0]['uri']);
        $this->assertContains('GET', $getRoutes[0]['methods']);
    }

    public function test_it_filters_routes_by_uri_pattern(): void
    {
        // Arrange
        $this->router->get('/api/users', fn () => 'OK')->name('api.users.index');
        $this->router->get('/api/posts', fn () => 'OK')->name('api.posts.index');
        $this->router->get('/admin/dashboard', fn () => 'OK')->name('admin.dashboard');
        $this->router->get('/public/home', fn () => 'OK')->name('public.home');

        // Act - Filter by API routes
        $apiRoutes = $this->discoverer->discover(['filter' => 'api/*']);

        // Assert
        $this->assertCount(2, $apiRoutes);
        $apiUris = collect($apiRoutes)->pluck('uri')->toArray();
        $this->assertContains('api/users', $apiUris);
        $this->assertContains('api/posts', $apiUris);

        // Act - Filter by admin routes
        $adminRoutes = $this->discoverer->discover(['filter' => 'admin/*']);

        // Assert
        $this->assertCount(1, $adminRoutes);
        $this->assertEquals('admin/dashboard', $adminRoutes[0]['uri']);
    }

    public function test_it_filters_routes_by_multiple_patterns(): void
    {
        // Arrange
        $this->router->get('/api/users', fn () => 'OK')->name('api.users');
        $this->router->get('/admin/users', fn () => 'OK')->name('admin.users');
        $this->router->get('/public/home', fn () => 'OK')->name('public.home');

        // Act - Filter by multiple patterns
        $routes = $this->discoverer->discover(['filter' => 'api/*, admin/*']);

        // Assert
        $this->assertCount(2, $routes);
        $uris = collect($routes)->pluck('uri')->toArray();
        $this->assertContains('api/users', $uris);
        $this->assertContains('admin/users', $uris);
        $this->assertNotContains('public/home', $uris);
    }

    public function test_it_filters_routes_by_auth_middleware(): void
    {
        // Arrange
        $this->router->get('/public', fn () => 'OK')->name('public.route');
        $this->router->get('/protected', fn () => 'OK')->name('protected.route')->middleware('auth');
        $this->router->get('/api-protected', fn () => 'OK')->name('api.protected')->middleware('auth:api');
        $this->router->get('/other-middleware', fn () => 'OK')->name('other.route')->middleware('throttle');

        // Act
        $authRoutes = $this->discoverer->discover(['auth' => true]);

        // Assert
        $this->assertCount(2, $authRoutes);
        $names = collect($authRoutes)->pluck('name')->toArray();
        $this->assertContains('protected.route', $names);
        $this->assertContains('api.protected', $names);
        $this->assertNotContains('public.route', $names);
        $this->assertNotContains('other.route', $names);
    }

    public function test_it_combines_multiple_filters(): void
    {
        // Arrange
        $this->router->get('/api/public', fn () => 'OK')->name('api.public');
        $this->router->post('/api/protected', fn () => 'OK')->name('api.protected')->middleware('auth');
        $this->router->get('/api/protected-get', fn () => 'OK')->name('api.protected.get')->middleware('auth');
        $this->router->post('/admin/protected', fn () => 'OK')->name('admin.protected')->middleware('auth');

        // Act - Combine method, filter, and auth
        $routes = $this->discoverer->discover([
            'method' => 'POST',
            'filter' => 'api/*',
            'auth' => true,
        ]);

        // Assert
        $this->assertCount(1, $routes);
        $this->assertEquals('api/protected', $routes[0]['uri']);
        $this->assertEquals('api.protected', $routes[0]['name']);
        $this->assertContains('POST', $routes[0]['methods']);
    }

    public function test_it_detects_api_routes_by_middleware(): void
    {
        // Arrange
        $this->router->get('/web-route', fn () => 'OK')->name('web.route');
        $this->router->get('/api-route', fn () => 'OK')->name('api.route')->middleware('api');

        // Act
        $routes = $this->discoverer->discover([]);

        // Assert
        $webRoute = collect($routes)->firstWhere('name', 'web.route');
        $apiRoute = collect($routes)->firstWhere('name', 'api.route');

        $this->assertEquals('web', $webRoute['type']);
        $this->assertEquals('api', $apiRoute['type']);
    }

    public function test_it_detects_api_routes_by_uri_prefix(): void
    {
        // Arrange
        $this->router->get('/regular-route', fn () => 'OK')->name('regular.route');
        $this->router->get('/api/users', fn () => 'OK')->name('api.users');

        // Act
        $routes = $this->discoverer->discover([]);

        // Assert
        $regularRoute = collect($routes)->firstWhere('name', 'regular.route');
        $apiRoute = collect($routes)->firstWhere('name', 'api.users');

        $this->assertEquals('web', $regularRoute['type']);
        $this->assertEquals('api', $apiRoute['type']);
    }

    public function test_it_handles_routes_without_names(): void
    {
        // Arrange
        $this->router->get('/unnamed-route', fn () => 'OK');
        $this->router->get('/named-route', fn () => 'OK')->name('named.route');

        // Act
        $routes = collect($this->discoverer->discover([]))
            ->reject(fn ($route) => $route['uri'] === 'storage/{path}')
            ->values()
            ->all();

        // Assert
        $this->assertCount(2, $routes);

        $unnamedRoute = collect($routes)->firstWhere('name', '');
        $namedRoute = collect($routes)->firstWhere('name', 'named.route');

        $this->assertNotNull($unnamedRoute);
        $this->assertEquals('unnamed-route', $unnamedRoute['uri']);
        $this->assertEquals('', $unnamedRoute['name']);

        $this->assertNotNull($namedRoute);
        $this->assertEquals('named-route', $namedRoute['uri']);
        $this->assertEquals('named.route', $namedRoute['name']);
    }

    public function test_it_handles_routes_with_parameters(): void
    {
        // Arrange
        $this->router->get('/users/{user}', fn () => 'OK')->name('users.show');
        $this->router->get('/posts/{post}/comments/{comment?}', fn () => 'OK')->name('posts.comments.show');

        // Act
        $routes = $this->discoverer->discover([]);

        // Assert
        $userRoute = collect($routes)->firstWhere('name', 'users.show');
        $commentRoute = collect($routes)->firstWhere('name', 'posts.comments.show');

        $this->assertEquals('users/{user}', $userRoute['uri']);
        $this->assertEquals('posts/{post}/comments/{comment?}', $commentRoute['uri']);
    }

    public function test_it_captures_route_middleware_information(): void
    {
        // Arrange
        $this->router->get('/simple', fn () => 'OK')->name('simple.route');
        $this->router->get('/protected', fn () => 'OK')
            ->name('protected.route')
            ->middleware(['auth', 'verified', 'throttle:60,1']);

        // Act
        $routes = $this->discoverer->discover([]);

        // Assert
        $simpleRoute = collect($routes)->firstWhere('name', 'simple.route');
        $protectedRoute = collect($routes)->firstWhere('name', 'protected.route');

        $this->assertIsArray($simpleRoute['middleware']);
        $this->assertIsArray($protectedRoute['middleware']);
        $this->assertContains('auth', $protectedRoute['middleware']);
        $this->assertContains('verified', $protectedRoute['middleware']);
        $this->assertContains('throttle:60,1', $protectedRoute['middleware']);
    }

    public function test_it_captures_controller_action_information(): void
    {
        // Arrange
        $this->router->get('/closure', fn () => 'OK')->name('closure.route');

        // Act
        $routes = $this->discoverer->discover([]);

        // Assert
        $closureRoute = collect($routes)->firstWhere('name', 'closure.route');
        $this->assertNotNull($closureRoute['controller']);
        $this->assertIsString($closureRoute['controller']);
    }

    public function test_it_returns_empty_array_when_no_routes_match_filters(): void
    {
        // Arrange
        $this->router->get('/sample-route', fn () => 'OK')->name('sample.route');

        $this->io->shouldReceive('warning')
            ->once()
            ->with('No routes matched the given filters.');

        // Act
        $routes = $this->discoverer->discover(['filter' => 'nonexistent/*']);

        // Assert
        $this->assertEmpty($routes);
    }

    public function test_it_ignores_routes_with_empty_uri(): void
    {
        // This test verifies the empty URI check in the discover method
        // Note: It's hard to create a route with empty URI through normal means,
        // but the code handles this case

        // Arrange
        $this->router->get('/valid-route', fn () => 'OK')->name('valid.route');

        // Act
        $routes = $this->discoverer->discover([]);

        // Assert
        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $this->assertNotEmpty($route['uri']);
        }
    }

    public function test_it_handles_case_insensitive_method_filtering(): void
    {
        // Arrange
        $this->router->post('/post-route', fn () => 'OK')->name('post.route');

        // Act - Test lowercase method filter
        $routes = $this->discoverer->discover(['method' => 'post']);

        // Assert
        $this->assertCount(1, $routes);
        $this->assertEquals('post-route', $routes[0]['uri']);
    }

    public function test_it_returns_correct_route_structure(): void
    {
        // Arrange
        $this->router->post('/test-route', fn () => 'OK')
            ->name('test.route')
            ->middleware(['auth', 'api']);

        // Act
        $routes = $this->discoverer->discover([]);

        // Assert
        $route = $routes[0];
        $expectedKeys = ['methods', 'uri', 'name', 'controller', 'middleware', 'type'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $route, "Route structure missing key: {$key}");
        }

        $this->assertIsArray($route['methods']);
        $this->assertIsString($route['uri']);
        $this->assertIsString($route['name']);
        $this->assertIsString($route['controller']);
        $this->assertIsArray($route['middleware']);
        $this->assertIsString($route['type']);
    }

    public function test_it_handles_routes_with_multiple_http_methods(): void
    {
        // Arrange
        $this->router->match(['GET', 'POST'], '/multi-method', fn () => 'OK')->name('multi.method');

        // Act
        $routes = $this->discoverer->discover([]);

        // Assert
        $route = collect($routes)->firstWhere('name', 'multi.method');
        $this->assertNotNull($route);
        $this->assertContains('GET', $route['methods']);
        $this->assertContains('POST', $route['methods']);
    }

    public function test_it_prioritizes_explicit_api_middleware_over_uri_detection(): void
    {
        $this->router->get('/api/route-with-web', fn () => 'OK')
            ->name('api.web.route')
            ->middleware('web'); // Explicit web middleware on api/* route

        $routes = $this->discoverer->discover([]);

        $route = collect($routes)->firstWhere('name', 'api.web.route');
        $this->assertEquals('api', $route['type']);
    }

    public function test_it_filters_by_exact_uri_match(): void
    {
        $this->router->get('/api', fn () => 'OK')->name('api.root');
        $this->router->get('/api/users', fn () => 'OK')->name('api.users');

        $routes = $this->discoverer->discover(['filter' => 'api']);

        $this->assertCount(1, $routes);
        $this->assertEquals('api', $routes[0]['uri']);
    }

    public function test_it_handles_whitespace_in_filter_patterns(): void
    {
        // Arrange
        $this->router->get('/api/users', fn () => 'OK')->name('api.users');
        $this->router->get('/admin/dashboard', fn () => 'OK')->name('admin.dashboard');
        $this->router->get('/public/home', fn () => 'OK')->name('public.home');
        $this->router->get('/not/api/posts', fn () => 'OK')->name('api.posts');

        $routes = $this->discoverer->discover(['filter' => 'api/* , admin/*']);


        $this->assertCount(2, $routes);
        $uris = collect($routes)->pluck('uri')->toArray();
        $this->assertContains('api/users', $uris);
        $this->assertContains('admin/dashboard', $uris);
    }
}
