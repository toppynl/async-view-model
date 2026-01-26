<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Profiler\TimeEpoch;

/** Tests for TimeEpoch */
final class TimeEpochTest extends TestCase
{
    public function testGetElapsedReturnsMillisecondsSinceConstruction(): void
    {
        $epoch = new TimeEpoch();
        usleep(10_000); // 10ms

        $elapsed = $epoch->getElapsed();

        static::assertGreaterThan(5.0, $elapsed);
        static::assertLessThan(50.0, $elapsed);
    }

    public function testResetClearsStartTime(): void
    {
        $epoch = new TimeEpoch();
        usleep(10_000);
        $first = $epoch->getElapsed();

        $epoch->reset();

        $afterReset = $epoch->getElapsed();
        static::assertLessThan($first, $afterReset);
    }
}
