<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Fixtures;

use Amp\Future;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Stub ViewModel used in parent templates for inheritance testing.
 *
 * @implements AsyncViewModel<\stdClass>
 */
final class ParentStubViewModel implements AsyncViewModel
{
    #[\Override]
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        $data = new \stdClass();
        $data->parent = true;
        return Future::complete($data);
    }
}
