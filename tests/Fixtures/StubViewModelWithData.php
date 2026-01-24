<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Fixtures;

use Amp\Future;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/**
 * Stub ViewModel with proper PHPDoc for testing ViewModelManager::get().
 */
final class StubViewModelWithData implements AsyncViewModel
{
    /**
     * @return Future<StubData>
     */
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        return Future::complete(new StubData());
    }
}
