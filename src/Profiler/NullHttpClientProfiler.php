<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Profiler;

/**
 * No-op HTTP client profiler for production environments.
 */
final class NullHttpClientProfiler implements HttpClientProfilerInterface
{
    public function start(
        string $requestId,
        string $method,
        string $url,
        array $headers = [],
    ): void {
        // No-op
    }

    public function finish(
        string $requestId,
        int $statusCode,
        array $responseHeaders,
        int $bodySize,
    ): void {
        // No-op
    }

    public function fail(string $requestId, \Throwable $exception): void
    {
        // No-op
    }

    public function getEntries(): array
    {
        return [];
    }

    public function getTotalTime(): float
    {
        return 0.0;
    }

    public function getCount(): int
    {
        return 0;
    }

    public function getErrorCount(): int
    {
        return 0;
    }
}
