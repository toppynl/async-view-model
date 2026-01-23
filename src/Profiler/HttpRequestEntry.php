<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Profiler;

/**
 * Immutable data transfer object for a single HTTP request timing.
 */
final readonly class HttpRequestEntry
{
    /**
     * @param float $startTime Microtime relative to request start (ms)
     * @param float $endTime Microtime relative to request start (ms)
     * @param 'success'|'error' $status
     * @param array<string, string> $requestHeaders
     * @param array<string, string> $responseHeaders
     */
    public function __construct(
        public string $requestId,
        public string $method,
        public string $url,
        public float $startTime,
        public float $endTime,
        public int $statusCode,
        public array $requestHeaders,
        public array $responseHeaders,
        public int $responseSize,
        public string $status,
        public ?string $errorMessage = null,
    ) {}

    /**
     * Duration in milliseconds.
     */
    public function getDuration(): float
    {
        return $this->endTime - $this->startTime;
    }

    /**
     * Short URL for display (host + path).
     */
    public function getShortUrl(): string
    {
        $parsed = parse_url($this->url);

        return ($parsed['host'] ?? '') . ($parsed['path'] ?? '');
    }

    /**
     * Get host from URL.
     */
    public function getHost(): string
    {
        $parsed = parse_url($this->url);

        return $parsed['host'] ?? '';
    }

    /**
     * Get path from URL.
     */
    public function getPath(): string
    {
        $parsed = parse_url($this->url);

        return $parsed['path'] ?? '/';
    }

    /**
     * Check if request was successful (2xx or 3xx).
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success' && $this->statusCode < 400;
    }

    /**
     * Check if request resulted in client error (4xx).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if request resulted in server error (5xx).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}
