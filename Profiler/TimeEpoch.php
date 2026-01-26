<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Profiler;

use Toppy\AsyncViewModel\ResetInterface;

/**
 * Shared time reference for profilers to ensure aligned timestamps.
 */
final class TimeEpoch implements ResetInterface
{
    private float $startTime;

    public function __construct()
    {
        $this->startTime = (float) hrtime(true) / 1_000_000;
    }

    /**
     * Get elapsed time in milliseconds since epoch start.
     */
    public function getElapsed(): float
    {
        return ((float) hrtime(true) / 1_000_000) - $this->startTime;
    }

    #[\Override]
    public function reset(): void
    {
        $this->startTime = (float) hrtime(true) / 1_000_000;
    }
}
