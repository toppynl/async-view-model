<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Cache;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Cache\CacheableViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

final class CacheableViewModelTest extends TestCase
{
    public function testInterfaceCanBeImplemented(): void
    {
        $viewModel = new class implements CacheableViewModel {
            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                return Future::complete(new \stdClass());
            }

            public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
            {
                return 'test_key';
            }

            public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array
            {
                return ['tag1', 'tag2'];
            }

            public function getMaxAge(): int
            {
                return 300;
            }

            public function getStaleWhileRevalidate(): int
            {
                return 3600;
            }

            public function getStaleIfError(): int
            {
                return 86400;
            }
        };

        $viewContext = ViewContext::create('EUR', 'en', false, false, null);
        $requestContext = RequestContext::create(['id' => 123], 'product');

        $this->assertSame('test_key', $viewModel->getCacheKey($viewContext, $requestContext));
        $this->assertSame(['tag1', 'tag2'], $viewModel->getCacheTags($viewContext, $requestContext));
        $this->assertSame(300, $viewModel->getMaxAge());
        $this->assertSame(3600, $viewModel->getStaleWhileRevalidate());
        $this->assertSame(86400, $viewModel->getStaleIfError());
    }
}
