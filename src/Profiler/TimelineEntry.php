<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Profiler;

/**
 * Immutable data transfer object for a single ViewModel resolution timing.
 */
final readonly class TimelineEntry
{
    /**
     * @param class-string $viewModelClass
     * @param float $startTime Microtime relative to request start (ms)
     * @param float $endTime Microtime relative to request start (ms)
     * @param 'pending'|'success'|'error'|'cached'|'stale'|'stale_error' $status
     * @param array<class-string> $dependencies
     */
    public function __construct(
        public string $viewModelClass,
        public float $startTime,
        public float $endTime,
        public string $status,
        public ?string $errorMessage,
        public array $dependencies,
    ) {}

    /**
     * Duration in milliseconds.
     */
    public function getDuration(): float
    {
        return $this->endTime - $this->startTime;
    }

    /**
     * Short class name for display.
     */
    public function getShortName(): string
    {
        $parts = explode('\\', $this->viewModelClass);
        return end($parts);
    }
}
