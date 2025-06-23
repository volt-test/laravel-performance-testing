<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Contracts;

use VoltTest\Laravel\VoltTestManager;

interface VoltTestCase
{
    /**
     * Define the test Scenario
     *
     * @param VoltTestManager $manager
     * @return void
     * */
    public function define(VoltTestManager $manager): void;
}
