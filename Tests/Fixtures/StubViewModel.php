<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Fixtures;

use Amp\Future;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Stub ViewModel for testing compile-time discovery.
 * Named variants allow testing multiple ViewModels in same template.
 */
final class StubViewModel implements AsyncViewModel
{
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        return Future::complete(['stub' => true]);
    }
}
