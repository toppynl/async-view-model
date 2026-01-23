<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Fixtures;

use Amp\Future;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Stub ViewModel used in parent templates for inheritance testing.
 */
final class ParentStubViewModel implements AsyncViewModel
{
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        return Future::complete(['parent' => true]);
    }
}
