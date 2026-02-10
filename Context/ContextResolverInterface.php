<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Context;

interface ContextResolverInterface
{
    public function setViewContext(ViewContext $context): void;

    public function setRequestContext(RequestContext $context): void;

    public function hasViewContext(): bool;

    public function hasRequestContext(): bool;

    /**
     * Get ViewContext, or empty default if not set.
     */
    public function getViewContext(): ViewContext;

    /**
     * Get RequestContext, or empty default if not set.
     */
    public function getRequestContext(): RequestContext;
}
