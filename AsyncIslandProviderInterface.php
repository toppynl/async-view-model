<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

use Amp\Future;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Contract for async view model data providers.
 *
 * Implementations fetch data asynchronously. The template path
 * and skeleton are determined by convention, not by the provider.
 */
interface AsyncIslandProviderInterface
{
    /**
     * Fetch data asynchronously.
     *
     * @return Future<mixed> The resolved data
     */
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future;

    /**
     * Unique cache key for this instance.
     * Used with Twig cache block.
     */
    public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string;

    /**
     * Tags for cache invalidation.
     *
     * @return string[] e.g., ['product_123', 'stock']
     */
    public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array;
}
