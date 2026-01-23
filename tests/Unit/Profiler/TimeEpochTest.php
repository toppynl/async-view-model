<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Profiler\TimeEpoch;

final class TimeEpochTest extends TestCase
{
    public function testGetElapsedReturnsMillisecondsSinceConstruction(): void
    {
        $epoch = new TimeEpoch();
        usleep(10_000); // 10ms

        $elapsed = $epoch->getElapsed();

        $this->assertGreaterThan(5.0, $elapsed);
        $this->assertLessThan(50.0, $elapsed);
    }

    public function testResetClearsStartTime(): void
    {
        $epoch = new TimeEpoch();
        usleep(10_000);
        $first = $epoch->getElapsed();

        $epoch->reset();

        $afterReset = $epoch->getElapsed();
        $this->assertLessThan($first, $afterReset);
    }
}
