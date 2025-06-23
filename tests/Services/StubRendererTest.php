<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Services;

use Illuminate\Support\Facades\File;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use VoltTest\Laravel\Services\StepCodeGenerator;
use VoltTest\Laravel\Services\StubRenderer;

class StubRendererTest extends TestCase
{
    protected StubRenderer $renderer;

    protected StepCodeGenerator $mockGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGenerator = Mockery::mock(StepCodeGenerator::class);
        $this->renderer = new StubRenderer($this->mockGenerator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_renders_basic_stub_with_class_name(): void
    {
        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->once()
            ->andReturn('// Generated routes code');

        $result = $this->renderer->render('UserTest', []);

        $this->assertStringContainsString('class UserTest implements VoltTestCase', $result);
        $this->assertStringContainsString('namespace App\\VoltTests;', $result);
        $this->assertStringContainsString('// Generated routes code', $result);
    }

    public function test_it_replaces_all_placeholders_correctly(): void
    {
        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn('// Test routes');

        $result = $this->renderer->render('OrderTest', []);

        // Check class name replacement
        $this->assertStringContainsString('class OrderTest', $result);
        $this->assertStringContainsString("->scenario('OrderTest'", $result);

        // Check namespace replacement
        $this->assertStringContainsString('namespace App\\VoltTests;', $result);

        // Check routes replacement
        $this->assertStringContainsString('// Test routes', $result);
    }

    public function test_it_passes_routes_to_step_code_generator(): void
    {
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'users',
                'name' => 'users.index',
                'type' => 'web',
            ],
            [
                'methods' => ['POST'],
                'uri' => 'users',
                'name' => 'users.store',
                'type' => 'web',
            ],
        ];

        $expectedCode = <<<'PHP'
        // Step 1 : Users.index
        $scenario->step('Users.index')
            ->get('/users')
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(200);

        // Step 2 : Users.store
        $scenario->step('Users.store')
            ->post('/users', [
            '_token' => '${csrf_token}',
                // Add form fields here
            ])
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(200);
PHP;

        $this->mockGenerator
            ->shouldReceive('generate')
            ->with($routes)
            ->once()
            ->andReturn($expectedCode);

        $result = $this->renderer->render('UserTest', $routes);

        $this->assertStringContainsString('Users.index', $result);
        $this->assertStringContainsString('Users.store', $result);
        $this->assertStringContainsString('csrf_token', $result);
    }

    public function test_it_handles_empty_routes_array(): void
    {
        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->once()
            ->andReturn('// No routes selected');

        $result = $this->renderer->render('EmptyTest', []);

        $this->assertStringContainsString('class EmptyTest', $result);
        $this->assertStringContainsString('// No routes selected', $result);
    }

    public function test_it_uses_custom_stub_when_available(): void
    {
        $customStub = <<<'EOF'
<?php

namespace {{ namespace }};

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class {{ class }} implements VoltTestCase
{
    /**
     * Custom stub template for {{ class }}.
     */
    public function define(VoltTestManager $manager): void
    {
        $scenario = $manager->scenario('{{ class }}', 'Custom performance test');

{{ routes }}

        // Custom comment
    }
}
EOF;

        // Mock the file existence and content - use any string ending with volttest.stub
        File::shouldReceive('exists')
            ->withArgs(function ($path) {
                return str_ends_with($path, 'volttest.stub');
            })
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->withArgs(function ($path) {
                return str_ends_with($path, 'volttest.stub');
            })
            ->once()
            ->andReturn($customStub);

        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn('// Custom routes');

        $result = $this->renderer->render('CustomTest', []);

        $this->assertStringContainsString('Custom stub template for CustomTest', $result);
        $this->assertStringContainsString('Custom performance test', $result);
        $this->assertStringContainsString('// Custom comment', $result);
        $this->assertStringContainsString('// Custom routes', $result);
    }

    public function test_it_falls_back_to_default_stub_when_custom_not_found(): void
    {
        // Mock file not existing - use any string ending with volttest.stub
        File::shouldReceive('exists')
            ->withArgs(function ($path) {
                return str_ends_with($path, 'volttest.stub');
            })
            ->once()
            ->andReturn(false);

        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn('// Default routes');

        $result = $this->renderer->render('DefaultTest', []);

        // Should use default stub content
        $this->assertStringContainsString('class DefaultTest implements VoltTestCase', $result);
        $this->assertStringContainsString('Performance test for application routes', $result);
        $this->assertStringContainsString('// Default routes', $result);
    }

    public function test_it_handles_special_characters_in_class_name(): void
    {
        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn('// Routes code');

        $result = $this->renderer->render('User_API_V2_Test', []);

        $this->assertStringContainsString('class User_API_V2_Test', $result);
        $this->assertStringContainsString("->scenario('User_API_V2_Test'", $result);
    }

    public function test_it_preserves_indentation_in_routes_section(): void
    {
        $indentedCode = <<<'PHP'
        // Step 1 : Users.index
        $scenario->step('Users.index')
            ->get('/users')
            ->expectStatus(200);
PHP;

        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn($indentedCode);

        $result = $this->renderer->render('UserTest', []);

        // Verify the generated code maintains proper indentation
        $this->assertStringContainsString('        // Step 1 : Users.index', $result);
        $this->assertStringContainsString('        $scenario->step(\'Users.index\')', $result);
        $this->assertStringContainsString('            ->get(\'/users\')', $result);
    }

    public function test_it_handles_multiline_route_code(): void
    {
        $multilineCode = <<<'PHP'
        // Step 1 : Users.store
        $scenario->step('Users.store')
            ->post('/users', [
                '_token' => '${csrf_token}',
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ])
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(201);

        // Step 2 : Users.index
        $scenario->step('Users.index')
            ->get('/users')
            ->expectStatus(200);
PHP;

        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn($multilineCode);

        $result = $this->renderer->render('UserTest', []);

        $this->assertStringContainsString('Users.store', $result);
        $this->assertStringContainsString('Users.index', $result);
        $this->assertStringContainsString('john@example.com', $result);
        $this->assertStringContainsString('expectStatus(201)', $result);
        $this->assertStringContainsString('expectStatus(200)', $result);
    }

    public function test_it_generates_valid_php_code(): void
    {
        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn('// Valid PHP code');

        $result = $this->renderer->render('ValidTest', []);

        // Check that the generated code starts with PHP opening tag
        $this->assertStringStartsWith('<?php', $result);

        // Check that it has proper namespace
        $this->assertStringContainsString('namespace App\\VoltTests;', $result);

        // Check that it imports required classes
        $this->assertStringContainsString('use VoltTest\\Laravel\\Contracts\\VoltTestCase;', $result);
        $this->assertStringContainsString('use VoltTest\\Laravel\\VoltTestManager;', $result);

        // Check that the class is properly defined
        $this->assertStringContainsString('class ValidTest implements VoltTestCase', $result);

        // Check that the define method exists
        $this->assertStringContainsString('public function define(VoltTestManager $manager): void', $result);
    }

    public function test_it_handles_empty_step_code_gracefully(): void
    {
        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn('');

        $result = $this->renderer->render('EmptyStepsTest', []);

        $this->assertStringContainsString('class EmptyStepsTest', $result);
        // The empty string should be replaced in the routes section
        $this->assertStringContainsString('$scenario = $manager->scenario(', $result);
    }

    public function test_it_does_not_double_replace_placeholders(): void
    {
        // Test that if the class name contains namespace-like text, it doesn't get double replaced
        $this->mockGenerator
            ->shouldReceive('generate')
            ->with([])
            ->andReturn('// Routes for {{ class }}');

        $result = $this->renderer->render('App_Test', []);

        // Should not replace {{ class }} in the generated routes code
        $this->assertStringContainsString('// Routes for {{ class }}', $result);
        $this->assertStringContainsString('class App_Test', $result);
    }

    protected function getStubPath(): string
    {
        return __DIR__ . '/../../src/Services/../../resources/stubs/volttest.stub';
    }
}
