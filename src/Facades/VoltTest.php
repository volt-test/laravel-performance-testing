<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use VoltTest\Laravel\Contracts\VoltTestCase;

/**
 * @method static \VoltTest\VoltTest getVoltTest()
 * @method static \VoltTest\Laravel\VoltTestManager addTestFromClass(string|VoltTestCase $className)
 * @method static \VoltTest\Laravel\VoltTestManager cloud()
 * @method static Collection getScenarios() :
 * @method static \VoltTest\TestResult|\VoltTest\CloudRun run(bool $streamOutput = false)
 *
 * @see \VoltTest\Laravel\VoltTestManager
 */

class VoltTest extends Facade
{
    /*
     * Get the registered name of the component
     *
     * @return string
     * */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-volttest';
    }
}
