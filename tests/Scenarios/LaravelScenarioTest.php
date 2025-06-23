<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Scenarios;

use Mockery;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use VoltTest\Laravel\Scenarios\LaravelScenario;
use VoltTest\Scenario;
use VoltTest\Step;

class LaravelScenarioTest extends TestCase
{
    protected LaravelScenario $laravelScenario;

    protected Scenario $mockScenario;

    protected Step $mockStep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockScenario = Mockery::mock(Scenario::class);
        $this->mockStep = Mockery::mock(Step::class);

        // Setup basic scenario expectations
        $this->mockScenario
            ->shouldReceive('autohandleCookies')
            ->once()
            ->andReturnSelf();

        $this->laravelScenario = new LaravelScenario($this->mockScenario);
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
        $app['config']->set('volttest.base_url', 'http://localhost:8000');
    }

    public function test_it_initializes_with_scenario_and_auto_handles_cookies(): void
    {
        // The constructor should have called autohandleCookies on the mock
        $this->assertInstanceOf(LaravelScenario::class, $this->laravelScenario);
    }

    public function test_it_creates_step_and_returns_self(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->with('Test Step')
            ->once()
            ->andReturn($this->mockStep);

        $result = $this->laravelScenario->step('Test Step');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_performs_get_request_with_base_url(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->with('Test Step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('get')
            ->with('http://localhost:8000/users')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->get('/users');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_performs_get_request_without_base_url_when_not_configured(): void
    {
        config(['volttest.base_url' => null]);

        $this->mockScenario
            ->shouldReceive('step')
            ->with('Test Step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('get')
            ->with('/users')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->get('/users');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_performs_get_request_with_headers(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('get')
            ->with('http://localhost:8000/users')
            ->once()
            ->andReturnSelf();

        $this->mockStep
            ->shouldReceive('header')
            ->with('Authorization', 'Bearer token')
            ->once()
            ->andReturnSelf();

        $this->mockStep
            ->shouldReceive('header')
            ->with('Accept', 'application/json')
            ->once()
            ->andReturnSelf();


        $result = $this->laravelScenario
            ->step('Test Step')
            ->get('/users', [
                'Authorization' => 'Bearer token',
                'Accept' => 'application/json',
            ]);

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_performs_get_request_with_full_url(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('get')
            ->with('https://api.example.com/users')
            ->once()
            ->andReturnSelf();


        $result = $this->laravelScenario
            ->step('Test Step')
            ->get('https://api.example.com/users');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_performs_post_request_with_array_data(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $expectedData = 'name=John&email=john@example.com';

        $this->mockStep
            ->shouldReceive('post')
            ->with('http://localhost:8000/users', $expectedData)
            ->once()
            ->andReturnSelf();

        $reult = $this->laravelScenario
            ->step('Test Step')
            ->post('/users', ['name' => 'John', 'email' => 'john@example.com']);

        $this->assertSame($this->laravelScenario, $reult);
    }

    public function test_it_performs_post_request_with_string_data(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('post')
            ->with('http://localhost:8000/users', 'raw data')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->post('/users', 'raw data');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_performs_put_request(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $expectedData = 'name=Jane';

        $this->mockStep
            ->shouldReceive('put')
            ->with('http://localhost:8000/users/1', $expectedData)
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->put('/users/1', ['name' => 'Jane']);

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_performs_patch_request(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $expectedData = 'name=Jane';

        $this->mockStep
            ->shouldReceive('patch')
            ->with('http://localhost:8000/users/1', $expectedData)
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->patch('/users/1', ['name' => 'Jane']);

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_performs_delete_request(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('delete')
            ->with('http://localhost:8000/users/1')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->delete('/users/1');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_adds_custom_header(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('header')
            ->with('X-Custom-Header', 'custom-value')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->header('X-Custom-Header', 'custom-value');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_expects_status_code(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('validateStatus')
            ->with('expect_status_201', 201)
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->expectStatus(201);

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_expects_status_code_with_custom_name(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('validateStatus')
            ->with('custom_status_check', 200)
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->expectStatus(200, 'custom_status_check');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_extracts_json_value(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('extractFromJson')
            ->with('user_id', '$.user.id')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->extractJson('user_id', '$.user.id');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_extracts_header_value(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('extractFromHeader')
            ->with('auth_token', 'Authorization')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->extractHeader('auth_token', 'Authorization');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_extracts_html_value(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('extractFromHtml')
            ->with('form_token', 'input[name="_token"]', 'value')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->extractHtml('form_token', 'input[name="_token"]', 'value');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_extracts_html_value_without_attribute(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('extractFromHtml')
            ->with('page_title', 'title', null)
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->extractHtml('page_title', 'title');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_extracts_regex_value(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('extractFromRegex')
            ->with('session_id', '/session_id=([a-f0-9]+)/')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->extractRegex('session_id', '/session_id=([a-f0-9]+)/');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_sets_think_time(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('setThinkTime')
            ->with('2s')
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->thinkTime('2s');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_sets_scenario_weight(): void
    {
        $this->mockScenario
            ->shouldReceive('setWeight')
            ->with(50)
            ->once()
            ->andReturnSelf();

        $result = $this->laravelScenario->weight(50);

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_skips_csrf_extraction_when_disabled(): void
    {
        config(['volttest.auto_csrf' => false]);

        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('get')
            ->once()
            ->andReturnSelf();

        $this->mockStep
            ->shouldNotReceive('extractFromHtml');

        $result = $this->laravelScenario
            ->step('Test Step')
            ->get('/users');

        $this->assertSame($this->laravelScenario, $result);
    }

    public function test_it_extracts_csrf_token_with_custom_variable_name(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('extractFromHtml')
            ->with('custom_token', 'input[name=_token]', 'value')
            ->once()
            ->andReturnSelf();

        $resule = $this->laravelScenario
            ->step('Test Step')
            ->extractCsrfToken('custom_token');

        $this->assertSame($this->laravelScenario, $resule);
    }

    public function test_it_returns_underlying_scenario(): void
    {
        $scenario = $this->laravelScenario->getScenario();

        $this->assertSame($this->mockScenario, $scenario);
    }

    public function test_it_throws_exception_when_no_step_exists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No step defined. Please define a step before calling this method.');

        $this->laravelScenario->get('/users');
    }

    public function test_it_throws_exception_for_header_without_step(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No step defined. Please define a step before calling this method.');

        $this->laravelScenario->header('X-Test', 'value');
    }

    public function test_it_throws_exception_for_expect_status_without_step(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No step defined. Please define a step before calling this method.');

        $this->laravelScenario->expectStatus(200);
    }

    public function test_it_handles_empty_data_in_post_request(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep
            ->shouldReceive('post')
            ->with('http://localhost:8000/users', '')
            ->once()
            ->andReturnSelf();

        $scenario = $this->laravelScenario
            ->step('Test Step')
            ->post('/users');

        // Assert that the scenario is returned correctly

        $this->assertSame($this->laravelScenario, $scenario);
    }

    public function test_it_supports_method_chaining(): void
    {
        $this->mockScenario
            ->shouldReceive('step')
            ->andReturn($this->mockStep);

        $this->mockStep->shouldIgnoreMissing();

        $result = $this->laravelScenario
            ->step('Test Step')
            ->get('/users')
            ->header('Accept', 'application/json')
            ->expectStatus(200)
            ->thinkTime('1s');

        $this->assertSame($this->laravelScenario, $result);
    }
}
