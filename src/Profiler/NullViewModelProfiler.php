<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Profiler;

use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * No-op profiler for production when profiling is disabled.
 */
final class NullViewModelProfiler implements ViewModelProfilerInterface
{
    public function start(
        string $viewModelClass,
        ViewContext $viewContext,
        RequestContext $requestContext,
        array $dependencies = [],
    ): void {
        // No-op
    }

    public function finish(string $viewModelClass, mixed $result): void
    {
        // No-op
    }

    public function fail(string $viewModelClass, \Throwable $exception): void
    {
        // No-op
    }

    public function recordCacheHit(string $viewModelClass, string $cacheStatus, float $startTime, float $endTime): void
    {
        // No-op
    }

    public function getEntries(): array
    {
        return [];
    }

    public function getParallelEfficiency(): float
    {
        return 1.0;
    }

    public function getTotalTime(): float
    {
        return 0.0;
    }
}
