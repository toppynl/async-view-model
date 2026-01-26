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
 *
 * @implements AsyncViewModel<\stdClass>
 */
final class StubViewModel implements AsyncViewModel
{
    #[\Override]
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        $data = new \stdClass();
        $data->stub = true;
        return Future::complete($data);
    }
}
