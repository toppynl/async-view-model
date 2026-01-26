<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Profiler;

/**
 * Collects timing data for HTTP client requests (AmPHP).
 */
interface HttpClientProfilerInterface
{
    /**
     * Mark the start of an HTTP request.
     *
     * @param array<string, string> $headers Request headers
     */
    public function start(string $requestId, string $method, string $url, array $headers = []): void;

    /**
     * Mark successful completion of an HTTP request.
     *
     * @param array<string, string> $responseHeaders
     */
    public function finish(string $requestId, int $statusCode, array $responseHeaders, int $bodySize): void;

    /**
     * Mark failed HTTP request.
     */
    public function fail(string $requestId, \Throwable $exception): void;

    /**
     * Get all collected HTTP request entries.
     *
     * @return array<HttpRequestEntry>
     */
    public function getEntries(): array;

    /**
     * Get total wall-clock time from first start to last finish.
     */
    public function getTotalTime(): float;

    /**
     * Get count of requests.
     */
    public function getCount(): int;

    /**
     * Get count of failed requests.
     */
    public function getErrorCount(): int;
}
