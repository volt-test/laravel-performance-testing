<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use VoltTest\Laravel\Contracts\VoltTestCase;

class TestClassDiscoverer
{
    /**
     * Resolve a test class name from input.
     * @param string $test
     * @return string|null
     */
    public function resolveTestClass(string $test): ?string
    {
        // If the full class name is provided
        if (class_exists($test)) {
            return $this->isVoltTestClass($test) ? $test : null;
        }

        // Try with App\VoltTests namespace
        $class = 'App\\VoltTests\\' . $test;
        if (class_exists($class) && $this->isVoltTestClass($class)) {
            return $class;
        }

        // Try with namespace and Test suffix
        if (! Str::endsWith($test, 'Test')) {
            $class = 'App\\VoltTests\\' . $test . 'Test';
            if (class_exists($class) && $this->isVoltTestClass($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Find all test classes in the configured paths.
     * @param string|null $customPath
     * @return array
     */
    public function findTestClasses(?string $customPath = null): array
    {
        $classes = [];
        $paths = $this->getTestPaths($customPath);

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $classes = array_merge($classes, $this->discoverClassesInPath($path));
        }

        return array_unique($classes);
    }

    /**
     * Get all test paths to search for test classes.
     * @param string|null $customPath
     * @return array
     */
    protected function getTestPaths(?string $customPath = null): array
    {
        if ($customPath) {
            return [base_path($customPath)];
        }

        $configuredPaths = config('volttest.test_paths', app_path('VoltTests'));

        return is_array($configuredPaths) ? $configuredPaths : [$configuredPaths];
    }

    /**
     * Discover classes in a specific path.
     * @param string $path
     * @return array
     */
    protected function discoverClassesInPath(string $path): array
    {
        $classes = [];

        $finder = new Finder();
        $finder->files()->name('*Test.php')->in($path);

        foreach ($finder as $file) {
            $className = $this->getClassNameFromFile($file->getRealPath());

            if ($className && $this->isVoltTestClass($className)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * Get class name from file path.
     * @param string $path
     * @return string|null
     */
    protected function getClassNameFromFile(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        // Extract namespace
        if (! preg_match('/namespace\s+([^;]+)/s', $content, $namespaceMatches)) {
            return null;
        }

        // Extract class name
        if (! preg_match('/class\s+(\w+)/s', $content, $classMatches)) {
            return null;
        }

        $namespace = trim($namespaceMatches[1]);
        $className = trim($classMatches[1]);

        return $namespace . '\\' . $className;
    }

    /**
     * Check if the class is a VoltTest class.
     * @param string $className
     * @return bool
     */
    protected function isVoltTestClass(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);

        return $reflection->implementsInterface(VoltTestCase::class);
    }
}
