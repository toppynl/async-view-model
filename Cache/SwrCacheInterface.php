<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Cache;

/**
 * Framework-agnostic cache interface for SWR caching.
 *
 * Implementations wrap platform-specific cache backends
 * (Symfony Cache, Laravel Cache, PSR-6, etc.)
 */
interface SwrCacheInterface
{
    /**
     * Retrieve a cache entry by key.
     *
     * @return CacheEntry|null The entry if found, null on miss
     */
    public function get(string $key): ?CacheEntry;

    /**
     * Store a cache entry with tags.
     *
     * TTL is derived from entry's getTotalTtl().
     *
     * @param string $key Cache key
     * @param CacheEntry $entry The entry to store
     * @param array<string> $tags Tags for invalidation
     */
    public function set(string $key, CacheEntry $entry, array $tags): void;

    /**
     * Invalidate all entries with any of the given tags.
     *
     * @param array<string> $tags Tags to invalidate
     */
    public function invalidateTags(array $tags): void;
}
