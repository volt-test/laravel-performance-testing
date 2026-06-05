<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests;

use Mockery;
use Orchestra\Testbench\TestCase;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Laravel\VoltTestManager;
use VoltTest\VoltTest;

class VoltTestManagerCloudTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('volttest.base_url', 'http://localhost:8000');
    }

    public function test_cloud_enables_with_valid_api_key(): void
    {
        $config = [
            'name' => 'Test',
            'cloud' => ['api_key' => 'vt_test_key_123'],
        ];

        $manager = new VoltTestManager($config);
        $result = $manager->cloud();

        $voltTest = $manager->getVoltTest();
        $reflection = new \ReflectionClass(VoltTest::class);
        $property = $reflection->getProperty('cloudApiKey');
        $property->setAccessible(true);

        $this->assertEquals('vt_test_key_123', $property->getValue($voltTest));
        $this->assertSame($manager, $result);
    }

    public function test_cloud_returns_self_for_chaining(): void
    {
        $config = [
            'cloud' => ['api_key' => 'vt_test_key_123'],
        ];

        $manager = new VoltTestManager($config);
        $result = $manager->cloud();

        $this->assertSame($manager, $result);
    }

    public function test_cloud_throws_when_api_key_null(): void
    {
        $config = [
            'cloud' => ['api_key' => null],
        ];

        $manager = new VoltTestManager($config);

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Cloud API key is required. Set VOLTTEST_API_KEY in your .env file.');

        $manager->cloud();
    }

    public function test_cloud_throws_when_api_key_empty(): void
    {
        $config = [
            'cloud' => ['api_key' => ''],
        ];

        $manager = new VoltTestManager($config);

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Cloud API key is required. Set VOLTTEST_API_KEY in your .env file.');

        $manager->cloud();
    }

    public function test_cloud_throws_when_cloud_config_missing(): void
    {
        $config = [];

        $manager = new VoltTestManager($config);

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Cloud API key is required. Set VOLTTEST_API_KEY in your .env file.');

        $manager->cloud();
    }

    public function test_run_method_is_callable(): void
    {
        $config = ['name' => 'Test'];
        $manager = new VoltTestManager($config);

        $this->assertIsCallable([$manager, 'run']);
    }
}
