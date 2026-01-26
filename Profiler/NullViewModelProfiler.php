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
    #[\Override]
    public function start(
        string $viewModelClass,
        ViewContext $viewContext,
        RequestContext $requestContext,
        array $dependencies = [],
    ): void {
        // No-op
    }

    #[\Override]
    public function finish(string $viewModelClass, mixed $result): void
    {
        // No-op
    }

    #[\Override]
    public function fail(string $viewModelClass, \Throwable $exception): void
    {
        // No-op
    }

    #[\Override]
    public function recordCacheHit(string $viewModelClass, string $cacheStatus, float $startTime, float $endTime): void
    {
        // No-op
    }

    #[\Override]
    public function getEntries(): array
    {
        return [];
    }

    #[\Override]
    public function getParallelEfficiency(): float
    {
        return 1.0;
    }

    #[\Override]
    public function getTotalTime(): float
    {
        return 0.0;
    }
}
