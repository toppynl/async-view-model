<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Fixtures;

use Amp\Future;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Second stub ViewModel for testing multiple ViewModels in templates.
 *
 * @implements AsyncViewModel<\stdClass>
 */
final class AnotherStubViewModel implements AsyncViewModel
{
    #[\Override]
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        $data = new \stdClass();
        $data->another = true;
        return Future::complete($data);
    }
}
