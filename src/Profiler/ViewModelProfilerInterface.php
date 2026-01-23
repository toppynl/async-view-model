<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Profiler;

use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Collects timing data for ViewModel resolutions.
 */
interface ViewModelProfilerInterface
{
    /**
     * Mark the start of a ViewModel resolution.
     *
     * @param class-string $viewModelClass
     * @param array<class-string> $dependencies
     */
    public function start(
        string $viewModelClass,
        ViewContext $viewContext,
        RequestContext $requestContext,
        array $dependencies = [],
    ): void;

    /**
     * Mark successful completion of a ViewModel resolution.
     *
     * @param class-string $viewModelClass
     */
    public function finish(string $viewModelClass, mixed $result): void;

    /**
     * Mark failed resolution of a ViewModel.
     *
     * @param class-string $viewModelClass
     */
    public function fail(string $viewModelClass, \Throwable $exception): void;

    /**
     * Record a cache hit (no resolution needed).
     *
     * @param class-string $viewModelClass
     * @param 'cached'|'stale'|'stale_error' $cacheStatus
     * @param float $startTime Start time relative to epoch (ms)
     * @param float $endTime End time relative to epoch (ms)
     */
    public function recordCacheHit(string $viewModelClass, string $cacheStatus, float $startTime, float $endTime): void;

    /**
     * Get all collected timeline entries.
     *
     * @return array<TimelineEntry>
     */
    public function getEntries(): array;

    /**
     * Calculate parallel efficiency ratio.
     *
     * Returns max(durations) / sum(durations).
     * 1.0 = perfect parallelism, lower = more sequential.
     *
     * @return float 0.0 to 1.0
     */
    public function getParallelEfficiency(): float;

    /**
     * Get total wall-clock time from first start to last finish.
     */
    public function getTotalTime(): float;
}
