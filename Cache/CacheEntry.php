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
    ) {}

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
