<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * View model that provides cache metadata for templates.
 *
 * The view model does NOT cache automatically. Instead, it provides cache key and tags
 * that templates can use to build their own Twig cache blocks:
 *
 *     {% set stock = view('App\\View\\StockViewModel') %}
 *     {% cache stock.cacheKey tags(stock.cacheTags) %}
 *         <div>{{ stock.data.quantity }} in stock</div>
 *     {% endcache %}
 */
interface WithCacheMetadata extends AsyncViewModel
{
    /**
     * Unique cache key for this view model instance.
     *
     * Used by templates to build Twig cache blocks.
     * Should incorporate all context that affects the output.
     */
    public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string;

    /**
     * Tags for cache invalidation.
     *
     * Used by templates in Twig cache blocks: tags(view.cacheTags)
     * When any tag is invalidated, the cached HTML is purged.
     *
     * @return array<string> e.g., ['product_123', 'stock', 'locale_en']
     */
    public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array;
}
