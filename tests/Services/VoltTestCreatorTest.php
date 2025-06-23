<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Services;

use Illuminate\Support\Facades\File;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Style\SymfonyStyle;
use VoltTest\Laravel\Services\RouteDiscoverer;
use VoltTest\Laravel\Services\StubRenderer;
use VoltTest\Laravel\Services\VoltTestCreator;

class VoltTestCreatorTest extends TestCase
{
    protected VoltTestCreator $creator;

    protected RouteDiscoverer $mockRouteDiscoverer;

    protected StubRenderer $mockStubRenderer;

    protected SymfonyStyle $mockIo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRouteDiscoverer = Mockery::mock(RouteDiscoverer::class);
        $this->mockStubRenderer = Mockery::mock(StubRenderer::class);
        $this->mockIo = Mockery::mock(SymfonyStyle::class);

        $this->creator = new VoltTestCreator(
            $this->mockRouteDiscoverer,
            $this->mockStubRenderer,
            $this->mockIo
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \VoltTest\Laravel\VoltTestServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('volttest.test_paths', '/path/to/tests');
    }

    public function test_it_creates_test_file_successfully(): void
    {
        $testPath = '/path/to/tests/UserTest.php';
        $generatedContent = '<?php class UserTest {}';

        // Mock route discovery
        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->with(['routes' => true])
            ->once()
            ->andReturn([]);

        // Mock stub rendering
        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('UserTest', [])
            ->once()
            ->andReturn($generatedContent);

        // Mock file operations
        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->once()
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->once()
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent)
            ->once();

        $result = $this->creator->create('User', ['routes' => true]);

        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_normalizes_test_name_by_adding_test_suffix(): void
    {
        $testPath = '/path/to/tests/UserTest.php';
        $generatedContent = '<?php class UserTest {}';

        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->andReturn([]);

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('UserTest', [])
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent);

        $result = $this->creator->create('User', ['routes' => false]);

        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_preserves_test_suffix_when_already_present(): void
    {
        $testPath = '/path/to/tests/UserTest.php';
        $generatedContent = '<?php class UserTest {}';

        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->andReturn([]);

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('UserTest', [])
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent);

        $result = $this->creator->create('UserTest', ['routes' => false]);

        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_creates_test_directory_if_it_does_not_exist(): void
    {
        $testPath = '/path/to/tests/UserTest.php';
        $generatedContent = '<?php class UserTest {}';

        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->andReturn([]);

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('UserTest', [])
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(false);

        File::shouldReceive('makeDirectory')
            ->with('/path/to/tests', 0755, true)
            ->once();

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent);

        $result = $this->creator->create('User', ['routes' => false]);

        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_prompts_for_overwrite_when_file_exists(): void
    {
        $testPath = '/path/to/tests/UserTest.php';

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(true);

        $this->mockIo
            ->shouldReceive('confirm')
            ->with('The test file already exists. Overwrite?', false)
            ->once()
            ->andReturn(false);

        $result = $this->creator->create('User', ['routes' => false]);

        $this->assertEquals('Test file creation aborted.', $result);
    }

    public function test_it_overwrites_file_when_confirmed(): void
    {
        $testPath = '/path/to/tests/UserTest.php';
        $generatedContent = '<?php class UserTest {}';

        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->andReturn([]);

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('UserTest', [])
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(true);

        $this->mockIo
            ->shouldReceive('confirm')
            ->with('The test file already exists. Overwrite?', false)
            ->once()
            ->andReturn(true);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent)
            ->once();

        $result = $this->creator->create('User', ['routes' => false]);

        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_handles_invalid_test_names(): void
    {
        // Test that validation happens before any file operations
        $result = $this->creator->create('', ['routes' => false]);
        $this->assertEquals('Invalid test name provided.', $result);

        $result = $this->creator->create('Invalid-Name!', ['routes' => false]);
        $this->assertEquals('Invalid test name provided.', $result);

        $result = $this->creator->create('Invalid Name', ['routes' => false]);
        $this->assertEquals('Invalid test name provided.', $result);

        // Test whitespace-only name
        $result = $this->creator->create('   ', ['routes' => false]);
        $this->assertEquals('Invalid test name provided.', $result);

        // Test names with special characters
        $result = $this->creator->create('Test@Name', ['routes' => false]);
        $this->assertEquals('Invalid test name provided.', $result);
    }

    public function test_it_rejects_names_starting_with_numbers(): void
    {
        // Names starting with numbers should be rejected (if source validation is fixed)
        $result = $this->creator->create('123Invalid', ['routes' => false]);
        $this->assertEquals('Invalid test name provided.', $result);

        $result = $this->creator->create('1Test', ['routes' => false]);
        $this->assertEquals('Invalid test name provided.', $result);
    }

    public function test_it_accepts_valid_test_names_with_underscores(): void
    {
        $testPath = '/path/to/tests/Valid_NameTest.php';
        $generatedContent = '<?php class Valid_NameTest {}';

        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->andReturn([]);

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('Valid_NameTest', [])
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent);

        $result = $this->creator->create('Valid_Name', ['routes' => false]);
        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_accepts_valid_test_names_with_numbers(): void
    {
        $testPath = '/path/to/tests/Valid123Test.php';
        $generatedContent = '<?php class Valid123Test {}';

        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->andReturn([]);

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('Valid123Test', [])
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent);

        $result = $this->creator->create('Valid123', ['routes' => false]);
        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_passes_options_to_route_discoverer(): void
    {
        $testPath = '/path/to/tests/UserTest.php';
        $generatedContent = '<?php class UserTest {}';
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'users',
                'name' => 'users.index',
                'type' => 'web',
            ],
        ];

        $options = [
            'routes' => true,
            'filter' => 'api/*',
            'method' => 'GET',
            'auth' => true,
            'select' => true,
        ];

        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->with($options)
            ->once()
            ->andReturn($routes);

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('UserTest', $routes)
            ->once()
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent);

        $result = $this->creator->create('User', $options);

        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_skips_route_discovery_when_routes_option_is_false(): void
    {
        $testPath = '/path/to/tests/UserTest.php';
        $generatedContent = '<?php class UserTest {}';

        $this->mockRouteDiscoverer
            ->shouldNotReceive('discover');

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('UserTest', [])
            ->once()
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with('/path/to/tests')
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent);

        $result = $this->creator->create('User', ['routes' => false]);

        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }

    public function test_it_uses_custom_path_configuration(): void
    {
        // Test with a specific known path
        $customPath = '/custom/volt/tests';
        $this->app['config']->set('volttest.test_paths', $customPath);

        $testPath = $customPath . '/UserTest.php';
        $generatedContent = '<?php class UserTest {}';

        $this->mockRouteDiscoverer
            ->shouldReceive('discover')
            ->andReturn([]);

        $this->mockStubRenderer
            ->shouldReceive('render')
            ->with('UserTest', [])
            ->andReturn($generatedContent);

        File::shouldReceive('exists')
            ->with($customPath)
            ->andReturn(true);

        File::shouldReceive('exists')
            ->with($testPath)
            ->andReturn(false);

        File::shouldReceive('put')
            ->with($testPath, $generatedContent);

        $result = $this->creator->create('User', ['routes' => false]);

        $this->assertEquals("VoltTest performance test created: {$testPath}", $result);
    }
}
