<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use Illuminate\Support\Facades\File;

class StubRenderer
{
    /**
     * Default stub content for VoltTest case.
     *
     * This stub is used when the custom stub file is not found.
     *
     * @var string
     */
    protected string $defaultStub = <<<'EOF'
<?php

namespace {{ namespace }};

use VoltTest\Laravel\Contracts\VoltTestCase;
use VoltTest\Laravel\VoltTestManager;

class {{ class }} implements VoltTestCase
{
    public function define(VoltTestManager $manager): void
    {
        $scenario = $manager->scenario('{{ class }}');

{{ routes }}

        // Add more steps as needed...
    }
}
EOF;

    public function __construct(protected StepCodeGenerator $generator)
    {
    }

    /**
     * Render the VoltTest stub with the provided class name and routes.
     *
     * @param string $name The name of the test class.
     * @param array $routes The routes to include in the test.
     * @return string The rendered stub content.
     */
    public function render(string $name, array $routes): string
    {
        $stub = $this->getStub();

        return str_replace(
            ['{{ class }}', '{{ namespace }}', '{{ rootNamespace }}', '{{ routes }}'],
            [$name, 'App\\VoltTests', 'App\\', $this->generator->generate($routes)],
            $stub
        );
    }

    /**
     * Get the stub content for the VoltTest case.
     *
     * This method checks if a custom stub file exists and returns its content.
     * If the file does not exist, it returns the default stub content.
     *
     * @return string The content of the stub file.
     */
    protected function getStub(): string
    {
        $path = __DIR__ . '/../../resources/stubs/volttest.stub';

        return File::exists($path) ? File::get($path) : $this->defaultStub;
    }
}
