<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Cache;

/**
 * Immutable cache entry with SWR metadata.
 *
 * Stores the cached value alongside timing information for
 * determining freshness and stale window eligibility.
 */
final readonly class CacheEntry
{
    public function __construct(
        public mixed $value,
        public int $createdAt,
        public int $maxAge,
        public int $staleWhileRevalidate,
        public int $staleIfError,
        public bool $isNegative = false,
    ) {}

    /**
     * Create a negative cache entry representing a legitimately empty resolution
     * (NoDataException), so the empty/404 case is served from cache instead of
     * re-hitting the source on every render.
     */
    public static function negative(
        int $createdAt,
        int $maxAge,
        int $staleWhileRevalidate = 0,
        int $staleIfError = 0,
    ): self {
        return new self(
            value: null,
            createdAt: $createdAt,
            maxAge: $maxAge,
            staleWhileRevalidate: $staleWhileRevalidate,
            staleIfError: $staleIfError,
            isNegative: true,
        );
    }

    /**
     * Age of the entry in seconds.
     */
    public function age(?int $now = null): int
    {
        return ($now ?? time()) - $this->createdAt;
    }

    /**
     * Entry is fresh - serve without revalidation.
     */
    public function isFresh(?int $now = null): bool
    {
        return $this->age($now) <= $this->maxAge;
    }

    /**
     * Entry is stale but within SWR window - serve while revalidating.
     */
    public function isStaleRevalidatable(?int $now = null): bool
    {
        return $this->age($now) <= ($this->maxAge + $this->staleWhileRevalidate);
    }

    /**
     * Entry can be served if revalidation fails.
     */
    public function isStaleServableOnError(?int $now = null): bool
    {
        return $this->age($now) <= ($this->maxAge + $this->staleIfError);
    }

    /**
     * Total TTL for cache backend storage.
     */
    public function getTotalTtl(): int
    {
        return $this->maxAge + max($this->staleWhileRevalidate, $this->staleIfError);
    }
}
