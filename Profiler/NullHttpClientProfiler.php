<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Profiler;

/**
 * No-op HTTP client profiler for production environments.
 */
final class NullHttpClientProfiler implements HttpClientProfilerInterface
{
    #[\Override]
    public function start(string $requestId, string $method, string $url, array $headers = []): void
    {
        // No-op
    }

    #[\Override]
    public function finish(string $requestId, int $statusCode, array $responseHeaders, int $bodySize): void
    {
        // No-op
    }

    #[\Override]
    public function fail(string $requestId, \Throwable $exception): void
    {
        // No-op
    }

    #[\Override]
    public function getEntries(): array
    {
        return [];
    }

    #[\Override]
    public function getTotalTime(): float
    {
        return 0.0;
    }

    #[\Override]
    public function getCount(): int
    {
        return 0;
    }

    #[\Override]
    public function getErrorCount(): int
    {
        return 0;
    }
}
