<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Style\SymfonyStyle;

class VoltTestCreator
{
    public function __construct(
        protected RouteDiscoverer $routeDiscoverer,
        protected StubRenderer $stubRenderer,
        protected SymfonyStyle $io
    ) {
    }

    /**
     * Create a new VoltTest performance test.
     *
     * @param string $name The name of the test.
     * @param array $options Options for the test creation, such as routes.
     * @return string The result message indicating success or failure.
     */
    public function create(string $name, array $options): string
    {
        $name = $this->validateAndNormalizeTestName($name);
        if ($name === null) {
            return 'Invalid test name provided.';
        }

        $directory = $this->ensureTestDirectory();
        $path = $directory . '/' . $name . '.php';

        if (File::exists($path)) {
            if (! $this->io->confirm('The test file already exists. Overwrite?', false)) {
                return 'Test file creation aborted.';
            }
        }

        $routes = $options['routes'] ? $this->routeDiscoverer->discover($options) : [];
        $content = $this->stubRenderer->render($name, $routes);
        File::put($path, $content);

        return "VoltTest performance test created: {$path}";
    }

    /**
     * Validate and normalize the test name.
     *
     * @param string $name The name of the test.
     * @return string|null The normalized test name or null if invalid.
     */
    protected function validateAndNormalizeTestName(string $name): ?string
    {
        $name = trim($name);

        // Fixed regex: Must start with letter or underscore, followed by letters, numbers, or underscores
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            return null;
        }

        return str_ends_with($name, 'Test') ? $name : $name . 'Test';
    }

    /**
     * Ensure the test directory exists, creating it if necessary.
     *
     * @return string The path to the test directory.
     */
    protected function ensureTestDirectory(): string
    {
        $directory = config('volttest.test_paths', app_path('VoltTests'));
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory;
    }
}
