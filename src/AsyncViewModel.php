<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

use Amp\Future;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Base interface for async view models.
 *
 * View models fetch data asynchronously using AmPHP Fibers.
 * They are discovered at compile-time by scanning Twig templates for view() calls.
 *
 * @template TData of object
 */
interface AsyncViewModel
{
    /**
     * Fetch data asynchronously.
     *
     * This method starts a non-blocking operation and returns a Future.
     * The Future is awaited when the template needs the data.
     *
     * @return Future<TData> The resolved data object
     */
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future;
}
