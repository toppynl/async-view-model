<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Cache;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Cache\CacheableViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

/** Tests for CacheableViewModel interface */
final class CacheableViewModelTest extends TestCase
{
    public function testInterfaceCanBeImplemented(): void
    {
        $viewModel = new class implements CacheableViewModel {
            #[\Override]
            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                return Future::complete(new \stdClass());
            }

            #[\Override]
            public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
            {
                return 'test_key';
            }

            #[\Override]
            public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array
            {
                return ['tag1', 'tag2'];
            }

            #[\Override]
            public function getMaxAge(): int
            {
                return 300;
            }

            #[\Override]
            public function getStaleWhileRevalidate(): int
            {
                return 3600;
            }

            #[\Override]
            public function getStaleIfError(): int
            {
                return 86400;
            }
        };

        $viewContext = ViewContext::create('EUR', 'en', false, false, null);
        $requestContext = RequestContext::create(['id' => 123], 'product');

        static::assertSame('test_key', $viewModel->getCacheKey($viewContext, $requestContext));
        static::assertSame(['tag1', 'tag2'], $viewModel->getCacheTags($viewContext, $requestContext));
        static::assertSame(300, $viewModel->getMaxAge());
        static::assertSame(3600, $viewModel->getStaleWhileRevalidate());
        static::assertSame(86400, $viewModel->getStaleIfError());
    }
}
