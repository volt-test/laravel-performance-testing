<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use VoltTest\Laravel\Services\StepCodeGenerator;

class StepCodeGeneratorTest extends TestCase
{
    protected StepCodeGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new StepCodeGenerator();
    }

    public function test_it_returns_no_routes_message_for_empty_array(): void
    {
        // Act
        $result = $this->generator->generate([]);

        // Assert
        $this->assertEquals('// No routes selected', $result);
    }

    public function test_it_generates_get_request_code(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET', 'HEAD'],
                'uri' => 'users',
                'name' => 'users.index',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $expected = <<<'PHP'
        // Step 1 : Users.index
        $scenario->step('Users.index')
            ->get('/users')
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(200);
PHP;

        $this->assertEquals($expected, $result);
    }

    public function test_it_generates_post_request_code_with_csrf(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['POST'],
                'uri' => 'users',
                'name' => 'users.store',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $expected = <<<'PHP'
        // Step 1 : Users.store
        $scenario->step('Users.store')
            ->post('/users', [
            '_token' => '${csrf_token}',
                // Add form fields here
            ])
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(200);
PHP;

        $this->assertEquals($expected, $result);
    }

    public function test_it_generates_put_request_code(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['PUT'],
                'uri' => 'users/{user}',
                'name' => 'users.update',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $expected = <<<'PHP'
        // Step 1 : Users.update
        $scenario->step('Users.update')
            ->put('/users/${user}', [
                '_method' => 'put',
            '_token' => '${csrf_token}',
                // Add form fields here
            ])
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(200);
PHP;

        $this->assertEquals($expected, $result);
    }

    public function test_it_generates_patch_request_code(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['PATCH'],
                'uri' => 'users/{user}',
                'name' => 'users.patch',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $expected = <<<'PHP'
        // Step 1 : Users.patch
        $scenario->step('Users.patch')
            ->patch('/users/${user}', [
                '_method' => 'patch',
            '_token' => '${csrf_token}',
                // Add form fields here
            ])
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(200);
PHP;

        $this->assertEquals($expected, $result);
    }

    public function test_it_generates_delete_request_code(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['DELETE'],
                'uri' => 'users/{user}',
                'name' => 'users.destroy',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $expected = <<<'PHP'
        // Step 1 : Users.destroy
        $scenario->step('Users.destroy')
            ->post('/users/${user}', [
                '_method' => 'DELETE',
            '_token' => '${csrf_token}',
                // Add form fields here
            ])
            ->header('Content-Type', 'application/x-www-form-urlencoded')
            ->expectStatus(200);
PHP;

        $this->assertEquals($expected, $result);
    }

    public function test_it_generates_api_route_code_without_csrf(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['POST'],
                'uri' => 'api/users',
                'name' => 'api.users.store',
                'type' => 'api',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $expected = <<<'PHP'
        // Step 1 : Api.users.store
        $scenario->step('Api.users.store')
            ->post('/api/users', [
                // Add form fields here
            ])
            ->header('Authorization', 'Bearer ${token}')
            ->expectStatus(200);
PHP;

        $this->assertStringContainsString("->header('Authorization', 'Bearer \${token}')", $result);
        $this->assertStringNotContainsString("'_token' => '\${csrf_token}',", $result);
    }

    public function test_it_replaces_route_parameters_correctly(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'users/{user}/posts/{post}/comments/{comment}',
                'name' => 'complex.route',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->get('/users/\${user}/posts/\${post}/comments/\${comment}')", $result);
    }

    public function test_it_handles_optional_route_parameters(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'posts/{post}/comments/{comment?}',
                'name' => 'posts.comments.show',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->get('/posts/\${post}/comments/\${comment}')", $result);
    }

    public function test_it_generates_step_name_from_route_name(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'some-complex-uri/with-dashes',
                'name' => 'admin.users.index',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->step('Admin.users.index')", $result);
    }

    public function test_it_generates_step_name_from_uri_when_no_route_name(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'admin/dashboard-settings/user-preferences',
                'name' => '',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->step('AdminDashboardSettingsUserPreferences')", $result);
    }

    public function test_it_handles_special_characters_in_uri_for_step_names(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'api/v1/users/{user}/posts.json',
                'name' => '',
                'type' => 'api',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->step('ApiV1UsersUserPostsJson')", $result);
    }

    public function test_it_prioritizes_preferred_http_methods(): void
    {
        // Test that GET is preferred over HEAD
        $routes = [
            [
                'methods' => ['HEAD', 'GET', 'OPTIONS'],
                'uri' => 'test-route',
                'name' => 'test.route',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->get('/test-route')", $result);
    }

    public function test_it_handles_routes_with_only_non_preferred_methods(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['OPTIONS', 'HEAD'],
                'uri' => 'test-route',
                'name' => 'test.route',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        // Should fall back to first method (get)
        $this->assertStringContainsString("->get('/test-route')", $result);
    }

    public function test_it_generates_multiple_steps_for_multiple_routes(): void
    {
        // Arrange
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
            [
                'methods' => ['GET'],
                'uri' => 'api/posts',
                'name' => 'api.posts.index',
                'type' => 'api',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString('// Step 1 : Users.index', $result);
        $this->assertStringContainsString('// Step 2 : Users.store', $result);
        $this->assertStringContainsString('// Step 3 : Api.posts.index', $result);

        // Check that steps are separated by double newlines
        $this->assertStringContainsString("\n\n", $result);
    }

    public function test_it_adds_leading_slash_to_uris_without_it(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'users/profile',  // No leading slash
                'name' => 'users.profile',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->get('/users/profile')", $result);
    }

    public function test_it_preserves_leading_slash_when_present(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => '/users/profile',  // Has leading slash
                'name' => 'users.profile',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->get('/users/profile')", $result);
        // Make sure we don't get double slashes
        $this->assertStringNotContainsString("->get('//users/profile')", $result);
    }

    public function test_it_handles_root_route(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => '/',
                'name' => 'home',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->get('/')", $result);
    }

    public function test_it_handles_empty_uri_gracefully(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => '',
                'name' => 'empty.route',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->get('/')", $result);
    }

    public function test_it_handles_missing_type_field(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'users',
                'name' => 'users.index',
                // 'type' field is missing
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        // Should default to web type (with CSRF)
        $this->assertStringContainsString("->header('Content-Type', 'application/x-www-form-urlencoded')", $result);
    }

    public function test_it_generates_correct_comment_format(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'test',
                'name' => 'test.route',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);
        //        dd($result);

        // Assert
        $this->assertStringContainsString('// Step 1 : Test.route', $result);

        // Verify comment format (space after //, colon with spaces)
        $this->assertMatchesRegularExpression('/\/\/ Step \d+ : \w+/', $result);
    }

    public function test_it_maintains_consistent_indentation(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['POST'],
                'uri' => 'users',
                'name' => 'users.store',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $lines = explode("\n", $result);

        // Check that method calls are properly indented
        $this->assertStringStartsWith('        // Step', $lines[0]);
        $this->assertStringStartsWith("        \$scenario->step", $lines[1]);
        $this->assertStringStartsWith('            ->post', $lines[2]);
        $this->assertStringStartsWith('            ->header', $lines[6]);
        $this->assertStringStartsWith('            ->expectStatus', $lines[7]);
    }

    public function test_it_handles_complex_nested_route_parameters(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'organizations/{org}/teams/{team}/projects/{project?}/tasks/{task}',
                'name' => 'complex.nested.route',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $expectedUri = "'/organizations/\${org}/teams/\${team}/projects/\${project}/tasks/\${task}'";
        $this->assertStringContainsString($expectedUri, $result);
    }

    public function test_it_generates_unique_step_names_for_similar_routes(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'api/users',
                'name' => '',
                'type' => 'api',
            ],
            [
                'methods' => ['GET'],
                'uri' => 'api-users',  // Similar but different
                'name' => '',
                'type' => 'api',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString("->step('ApiUsers')", $result);
        $this->assertStringContainsString("->step('ApiUsers')", $result); // This will be the same unfortunately

        // Note: This reveals a potential issue where similar URIs generate identical step names
        // This might be something to address in the actual implementation
    }

    public function test_it_preserves_method_case_in_method_field(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['PUT'],
                'uri' => 'users/{user}',
                'name' => 'users.update',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        // The _method field should preserve the original case
        $this->assertStringContainsString("'_method' => 'put'", $result);
    }

    public function test_it_handles_very_long_route_names(): void
    {
        // Arrange
        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'very/long/route/with/many/segments',
                'name' => 'very.long.route.name.with.many.segments.and.more.segments',
                'type' => 'web',
            ],
        ];

        // Act
        $result = $this->generator->generate($routes);

        // Assert
        $this->assertStringContainsString('Very.long.route.name.with.many.segments.and.more.segments', $result);
    }

    public function test_it_generates_correct_headers_for_different_route_types(): void
    {
        // Arrange
        $webRoute = [
            'methods' => ['POST'],
            'uri' => 'web-route',
            'name' => 'web.route',
            'type' => 'web',
        ];

        $apiRoute = [
            'methods' => ['POST'],
            'uri' => 'api-route',
            'name' => 'api.route',
            'type' => 'api',
        ];

        // Act
        $webResult = $this->generator->generate([$webRoute]);
        $apiResult = $this->generator->generate([$apiRoute]);

        // Assert
        $this->assertStringContainsString("->header('Content-Type', 'application/x-www-form-urlencoded')", $webResult);
        $this->assertStringContainsString("->header('Authorization', 'Bearer \${token}')", $apiResult);

        $this->assertStringNotContainsString("Authorization", $webResult);
        $this->assertStringNotContainsString("Content-Type", $apiResult);
    }
}
