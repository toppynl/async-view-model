<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Default implementation for WithCacheMetadata::getCacheKey().
 *
 * Generates cache key from class name + sorted tags hash.
 * Classes using this trait must implement getCacheTags().
 */
trait CacheMetadataBehaviour
{
    /**
     * Tags for cache invalidation.
     *
     * @return array<string>
     */
    abstract public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array;

    public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
    {
        $tags = $this->getCacheTags($viewContext, $requestContext);
        sort($tags);

        return static::class . '_' . hash('xxh3', implode('|', $tags));
    }
}
