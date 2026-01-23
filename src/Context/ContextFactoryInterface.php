<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Context;

interface ContextFactoryInterface
{
    /**
     * Create ViewContext from current request.
     */
    public function createViewContext(bool $isPrivate = false): ViewContext;

    /**
     * Create RequestContext from current request.
     *
     * @param array<string, mixed> $additionalParams Extra params to merge with route params
     */
    public function createRequestContext(array $additionalParams = []): RequestContext;
}
