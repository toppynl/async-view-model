<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Cache;

use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * ViewModel that supports SWR caching at the resolution layer.
 *
 * TTL semantics follow HTTP Cache-Control:
 * - maxAge: fresh window (serve without revalidation)
 * - staleWhileRevalidate: seconds after maxAge to serve stale while revalidating async
 * - staleIfError: seconds after maxAge to serve stale if revalidation fails
 *
 * Both stale windows are additive offsets from maxAge, not sequential.
 */
interface CacheableViewModel extends AsyncViewModel
{
    /**
     * Unique cache key for this ViewModel instance.
     *
     * Should incorporate all context that affects the output.
     * Example: "product_stock_123_EUR"
     */
    public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string;

    /**
     * Tags for cache invalidation.
     *
     * When any tag is invalidated via the API, cached data is purged.
     *
     * @return array<string> e.g., ['product_123', 'stock', 'warehouse_eu']
     */
    public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array;

    /**
     * Fresh TTL in seconds (max-age equivalent).
     *
     * During this window, cached data is returned without revalidation.
     */
    public function getMaxAge(): int;

    /**
     * Stale-while-revalidate window in seconds.
     *
     * After maxAge expires, serve stale data while revalidating in background.
     * Set to 0 to disable SWR (block on revalidation).
     */
    public function getStaleWhileRevalidate(): int;

    /**
     * Stale-if-error window in seconds.
     *
     * After maxAge expires, serve stale data if revalidation fails.
     * Set to 0 to propagate errors immediately.
     */
    public function getStaleIfError(): int;
}
