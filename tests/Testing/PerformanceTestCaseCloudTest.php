<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Tests\Testing;

use PHPUnit\Framework\TestCase;
use VoltTest\Laravel\Testing\PerformanceTestCase;

class PerformanceTestCaseCloudTest extends TestCase
{
    public function testRunVoltTestReturnTypeIncludesCloudRun(): void
    {
        $method = new \ReflectionMethod(PerformanceTestCase::class, 'runVoltTest');
        $returnType = $method->getReturnType();

        $this->assertInstanceOf(\ReflectionUnionType::class, $returnType);

        $typeNames = array_map(
            fn (\ReflectionNamedType $t) => $t->getName(),
            $returnType->getTypes()
        );

        $this->assertContains('VoltTest\TestResult', $typeNames);
        $this->assertContains('VoltTest\CloudRun', $typeNames);
    }

    public function testLoadTestUrlReturnTypeIncludesCloudRun(): void
    {
        $method = new \ReflectionMethod(PerformanceTestCase::class, 'loadTestUrl');
        $returnType = $method->getReturnType();

        $this->assertInstanceOf(\ReflectionUnionType::class, $returnType);

        $typeNames = array_map(
            fn (\ReflectionNamedType $t) => $t->getName(),
            $returnType->getTypes()
        );

        $this->assertContains('VoltTest\TestResult', $typeNames);
        $this->assertContains('VoltTest\CloudRun', $typeNames);
    }

    public function testLoadTestApiReturnTypeIncludesCloudRun(): void
    {
        $method = new \ReflectionMethod(PerformanceTestCase::class, 'loadTestApi');
        $returnType = $method->getReturnType();

        $this->assertInstanceOf(\ReflectionUnionType::class, $returnType);

        $typeNames = array_map(
            fn (\ReflectionNamedType $t) => $t->getName(),
            $returnType->getTypes()
        );

        $this->assertContains('VoltTest\TestResult', $typeNames);
        $this->assertContains('VoltTest\CloudRun', $typeNames);
    }
}
