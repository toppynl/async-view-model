<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Cache;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Cache\CacheableViewModel;
use Toppy\AsyncViewModel\Cache\CacheEntry;
use Toppy\AsyncViewModel\Cache\CachingViewModelDecorator;
use Toppy\AsyncViewModel\Cache\SwrCacheInterface;
use Toppy\AsyncViewModel\Context\ContextResolverInterface;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;
use Toppy\AsyncViewModel\Profiler\TimeEpoch;
use Toppy\AsyncViewModel\Profiler\ViewModelProfilerInterface;
use Toppy\AsyncViewModel\ViewModelManagerInterface;

final class CachingViewModelDecoratorTest extends TestCase
{
    private ViewContext $viewContext;
    private RequestContext $requestContext;

    protected function setUp(): void
    {
        $this->viewContext = ViewContext::create('EUR', 'en', false, false, null);
        $this->requestContext = RequestContext::create(['id' => 123], 'product');
    }

    public function testNonCacheableViewModelPassesThrough(): void
    {
        $expectedResult = new \stdClass();
        $expectedResult->name = 'test';

        $inner = $this->createMock(ViewModelManagerInterface::class);
        $inner->expects($this->once())
            ->method('get')
            ->with('NonCacheable')
            ->willReturn($expectedResult);

        $viewModel = $this->createMock(AsyncViewModel::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $result = $decorator->get('NonCacheable');

        $this->assertSame($expectedResult, $result);
    }

    public function testViewModelNotInContainerPassesThrough(): void
    {
        $expectedResult = new \stdClass();
        $expectedResult->name = 'passthrough';

        $inner = $this->createMock(ViewModelManagerInterface::class);
        $inner->expects($this->once())
            ->method('get')
            ->with('UnknownViewModel')
            ->willReturn($expectedResult);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $result = $decorator->get('UnknownViewModel');

        $this->assertSame($expectedResult, $result);
    }

    public function testFreshCacheHitReturnsWithoutResolution(): void
    {
        $cachedValue = new \stdClass();
        $cachedValue->cached = true;

        $entry = new CacheEntry($cachedValue, time(), 300, 3600, 86400);

        $viewModel = $this->createCacheableViewModel();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn($entry);

        $inner = $this->createMock(ViewModelManagerInterface::class);
        $inner->expects($this->never())->method('get');

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $result = $decorator->get('CacheableViewModel');

        $this->assertSame($cachedValue, $result);
    }

    public function testCacheMissResolvesAndStores(): void
    {
        $freshValue = new \stdClass();
        $freshValue->fresh = true;

        $viewModel = $this->createCacheableViewModel($freshValue);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(
                'test_key',
                $this->callback(fn($e) => $e instanceof CacheEntry && $e->value === $freshValue),
                ['tag1'],
            );

        $inner = $this->createMock(ViewModelManagerInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $result = $decorator->get('CacheableViewModel');

        $this->assertSame($freshValue, $result);
    }

    public function testStaleRevalidatableReturnsStaleAndTriggersBackgroundRevalidation(): void
    {
        $staleValue = new \stdClass();
        $staleValue->stale = true;

        // Entry is past maxAge but within SWR window
        $entry = new CacheEntry($staleValue, time() - 400, 300, 3600, 86400);

        $viewModel = $this->createCacheableViewModel();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn($entry);
        // Background revalidation should store new value
        $cache->expects($this->once())->method('set');

        $inner = $this->createMock(ViewModelManagerInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $result = $decorator->get('CacheableViewModel');

        // Should return stale value immediately
        $this->assertSame($staleValue, $result);

        // Wait a bit for async revalidation to complete
        \Amp\delay(0.1);
    }

    public function testStaleIfErrorServesStaleOnFailure(): void
    {
        $staleValue = new \stdClass();
        $staleValue->stale = true;

        // Entry is past maxAge and past SWR window but within error window
        $entry = new CacheEntry($staleValue, time() - 4000, 300, 3600, 86400);

        $viewModel = $this->createFailingCacheableViewModel();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn($entry);

        $inner = $this->createMock(ViewModelManagerInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $result = $decorator->get('CacheableViewModel');

        $this->assertSame($staleValue, $result);
    }

    public function testExpiredEntryThrowsOnFailure(): void
    {
        // Entry is fully expired (past all windows)
        $entry = new CacheEntry(new \stdClass(), time() - 100000, 300, 3600, 86400);

        $viewModel = $this->createFailingCacheableViewModel();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn($entry);

        $inner = $this->createMock(ViewModelManagerInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated failure');

        $decorator->get('CacheableViewModel');
    }

    public function testCacheMissWithFailureAndNoExistingEntryThrows(): void
    {
        $viewModel = $this->createFailingCacheableViewModel();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn(null);

        $inner = $this->createMock(ViewModelManagerInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated failure');

        $decorator->get('CacheableViewModel');
    }

    public function testPreloadDelegatesToInner(): void
    {
        $inner = $this->createMock(ViewModelManagerInterface::class);
        $inner->expects($this->once())->method('preload')->with('SomeClass');

        $container = $this->createMock(ContainerInterface::class);
        $cache = $this->createMock(SwrCacheInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $decorator->preload('SomeClass');
    }

    public function testPreloadAllDelegatesToInner(): void
    {
        $classes = ['Class1', 'Class2', 'Class3'];

        $inner = $this->createMock(ViewModelManagerInterface::class);
        $inner->expects($this->once())->method('preloadAll')->with($classes);

        $container = $this->createMock(ContainerInterface::class);
        $cache = $this->createMock(SwrCacheInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $decorator->preloadAll($classes);
    }

    public function testPreloadWithFutureDelegatesToInner(): void
    {
        $expectedFuture = Future::complete(new \stdClass());

        $inner = $this->createMock(ViewModelManagerInterface::class);
        $inner->expects($this->once())
            ->method('preloadWithFuture')
            ->with('SomeClass')
            ->willReturn($expectedFuture);

        $container = $this->createMock(ContainerInterface::class);
        $cache = $this->createMock(SwrCacheInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $result = $decorator->preloadWithFuture('SomeClass');

        $this->assertSame($expectedFuture, $result);
    }

    public function testCacheEntryMetadataIsStoredCorrectly(): void
    {
        $freshValue = new \stdClass();

        $viewModel = $this->createCacheableViewModelWithCustomTtl(
            $freshValue,
            maxAge: 600,
            swr: 1800,
            staleIfError: 7200,
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $storedEntry = null;
        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $this->callback(function ($entry) use (&$storedEntry) {
                    $storedEntry = $entry;
                    return $entry instanceof CacheEntry;
                }),
                $this->anything(),
            );

        $inner = $this->createMock(ViewModelManagerInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $decorator->get('CacheableViewModel');

        $this->assertInstanceOf(CacheEntry::class, $storedEntry);
        $this->assertSame($freshValue, $storedEntry->value);
        $this->assertSame(600, $storedEntry->maxAge);
        $this->assertSame(1800, $storedEntry->staleWhileRevalidate);
        $this->assertSame(7200, $storedEntry->staleIfError);
    }

    public function testCustomCacheKeyIsUsed(): void
    {
        $freshValue = new \stdClass();

        $viewModel = new class($freshValue) implements CacheableViewModel {
            public function __construct(private readonly object $value) {}

            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                return Future::complete($this->value);
            }

            public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
            {
                return 'custom_key_' . $requestContext->get('id') . '_' . $viewContext->getCurrency();
            }

            public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array
            {
                return ['tag1'];
            }

            public function getMaxAge(): int { return 300; }
            public function getStaleWhileRevalidate(): int { return 3600; }
            public function getStaleIfError(): int { return 86400; }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(
                'custom_key_123_EUR',
                $this->anything(),
                $this->anything(),
            );

        $inner = $this->createMock(ViewModelManagerInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $decorator->get('CacheableViewModel');
    }

    public function testCacheTagsAreStoredCorrectly(): void
    {
        $freshValue = new \stdClass();

        $viewModel = new class($freshValue) implements CacheableViewModel {
            public function __construct(private readonly object $value) {}

            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                return Future::complete($this->value);
            }

            public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
            {
                return 'test_key';
            }

            public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array
            {
                return ['product_123', 'stock', 'warehouse_eu'];
            }

            public function getMaxAge(): int { return 300; }
            public function getStaleWhileRevalidate(): int { return 3600; }
            public function getStaleIfError(): int { return 86400; }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $cache = $this->createMock(SwrCacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $this->anything(),
                ['product_123', 'stock', 'warehouse_eu'],
            );

        $inner = $this->createMock(ViewModelManagerInterface::class);

        $decorator = new CachingViewModelDecorator(
            $inner,
            $container,
            $cache,
            $this->createContextResolver(),
            $this->createProfiler(),
            new TimeEpoch(),
        );

        $decorator->get('CacheableViewModel');
    }

    private function createContextResolver(): ContextResolverInterface
    {
        $resolver = $this->createMock(ContextResolverInterface::class);
        $resolver->method('getViewContext')->willReturn($this->viewContext);
        $resolver->method('getRequestContext')->willReturn($this->requestContext);
        return $resolver;
    }

    private function createProfiler(): ViewModelProfilerInterface
    {
        return $this->createMock(ViewModelProfilerInterface::class);
    }

    private function createCacheableViewModel(?object $returnValue = null): CacheableViewModel
    {
        $value = $returnValue ?? new \stdClass();

        return new class($value) implements CacheableViewModel {
            public function __construct(private readonly object $value) {}

            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                return Future::complete($this->value);
            }

            public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
            {
                return 'test_key';
            }

            public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array
            {
                return ['tag1'];
            }

            public function getMaxAge(): int { return 300; }
            public function getStaleWhileRevalidate(): int { return 3600; }
            public function getStaleIfError(): int { return 86400; }
        };
    }

    private function createCacheableViewModelWithCustomTtl(
        object $returnValue,
        int $maxAge,
        int $swr,
        int $staleIfError,
    ): CacheableViewModel {
        return new class($returnValue, $maxAge, $swr, $staleIfError) implements CacheableViewModel {
            public function __construct(
                private readonly object $value,
                private readonly int $maxAge,
                private readonly int $swr,
                private readonly int $staleIfError,
            ) {}

            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                return Future::complete($this->value);
            }

            public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
            {
                return 'test_key';
            }

            public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array
            {
                return ['tag1'];
            }

            public function getMaxAge(): int { return $this->maxAge; }
            public function getStaleWhileRevalidate(): int { return $this->swr; }
            public function getStaleIfError(): int { return $this->staleIfError; }
        };
    }

    private function createFailingCacheableViewModel(): CacheableViewModel
    {
        return new class implements CacheableViewModel {
            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                throw new \RuntimeException('Simulated failure');
            }

            public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
            {
                return 'test_key';
            }

            public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array
            {
                return ['tag1'];
            }

            public function getMaxAge(): int { return 300; }
            public function getStaleWhileRevalidate(): int { return 0; }
            public function getStaleIfError(): int { return 86400; }
        };
    }
}
