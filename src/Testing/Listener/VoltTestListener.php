<?php

declare(strict_types=1);

namespace VoltTest\Laravel\Testing\Listener;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

class VoltTestListener implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        // TODO: Implement notify() method.
        // Adding  VoltTest Results and integrate it with PHPUnit Events
    }
}
