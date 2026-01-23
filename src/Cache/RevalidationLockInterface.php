<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Cache;

/**
 * Lock interface to prevent concurrent revalidations of the same cache key.
 *
 * Used to avoid thundering herd / cache stampede when multiple requests
 * hit a stale cache entry simultaneously.
 */
interface RevalidationLockInterface
{
    /**
     * Attempt to acquire a non-blocking lock for revalidation.
     *
     * @param string $key The cache key being revalidated
     * @return bool True if lock acquired, false if already locked
     */
    public function acquire(string $key): bool;

    /**
     * Release the revalidation lock.
     *
     * @param string $key The cache key to unlock
     */
    public function release(string $key): void;
}
