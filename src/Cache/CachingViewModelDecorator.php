<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Cache;

use Amp\Future;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Toppy\AsyncViewModel\Context\ContextResolverInterface;
use Toppy\AsyncViewModel\Profiler\TimeEpoch;
use Toppy\AsyncViewModel\Profiler\ViewModelProfilerInterface;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\AsyncViewModel\WithDependencies;

/**
 * Decorator that adds SWR caching to ViewModelManager.
 *
 * Only caches ViewModels implementing CacheableViewModel.
 * Non-cacheable ViewModels pass through unchanged.
 */
final class CachingViewModelDecorator implements ViewModelManagerInterface
{
    public function __construct(
        private readonly ViewModelManagerInterface $inner,
        private readonly ContainerInterface $viewModels,
        private readonly SwrCacheInterface $cache,
        private readonly ContextResolverInterface $contextResolver,
        private readonly ViewModelProfilerInterface $profiler,
        private readonly TimeEpoch $epoch,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?RevalidationLockInterface $revalidationLock = null,
    ) {}

    public function preload(string $class): void
    {
        $this->inner->preload($class);
    }

    public function preloadAll(array $classes): void
    {
        $this->inner->preloadAll($classes);
    }

    public function preloadWithFuture(string $class): Future
    {
        return $this->inner->preloadWithFuture($class);
    }

    public function get(string $class): object
    {
        if (!$this->viewModels->has($class)) {
            return $this->inner->get($class);
        }

        $viewModel = $this->viewModels->get($class);

        if (!$viewModel instanceof CacheableViewModel) {
            return $this->inner->get($class);
        }

        $startTime = $this->epoch->getElapsed();

        $viewContext = $this->contextResolver->getViewContext();
        $requestContext = $this->contextResolver->getRequestContext();
        $key = $viewModel->getCacheKey($viewContext, $requestContext);

        $entry = $this->cache->get($key);

        if ($entry instanceof CacheEntry) {
            if ($entry->isFresh()) {
                $endTime = $this->epoch->getElapsed();
                $this->logger->debug('Cache hit (fresh)', ['key' => $key]);
                $this->profiler->recordCacheHit($class, 'cached', $startTime, $endTime);
                return $entry->value;
            }

            if ($entry->isStaleRevalidatable()) {
                $endTime = $this->epoch->getElapsed();
                $this->triggerRevalidation($viewModel, $key);
                $this->profiler->recordCacheHit($class, 'stale', $startTime, $endTime);
                return $entry->value;
            }
        }

        $this->logger->debug('Cache miss', ['key' => $key]);
        return $this->fetchAndStore($viewModel, $key, $entry);
    }

    private function triggerRevalidation(CacheableViewModel $viewModel, string $key): void
    {
        // If no lock configured, always revalidate (backward compatible)
        if ($this->revalidationLock === null) {
            $this->logger->debug('Cache hit (stale), revalidating', ['key' => $key]);
            \Amp\async(fn() => $this->revalidate($viewModel, $key));
            return;
        }

        // Try to acquire lock - only one request should revalidate
        if (!$this->revalidationLock->acquire($key)) {
            $this->logger->debug('Cache hit (stale), revalidation already in progress', ['key' => $key]);
            return;
        }

        $this->logger->debug('Cache hit (stale), revalidating with lock', ['key' => $key]);
        \Amp\async(fn() => $this->revalidateWithLock($viewModel, $key));
    }

    private function revalidate(CacheableViewModel $viewModel, string $key): void
    {
        $viewContext = $this->contextResolver->getViewContext();
        $requestContext = $this->contextResolver->getRequestContext();
        $class = $viewModel::class;

        $dependencies = $viewModel instanceof WithDependencies
            ? $viewModel->getDependencies()
            : [];

        $this->profiler->start($class, $viewContext, $requestContext, $dependencies);

        try {
            $future = $viewModel->resolve($viewContext, $requestContext);
            $value = $future->await();
            $this->profiler->finish($class, $value);
            $this->store($viewModel, $key, $value);
            $this->logger->debug('Background revalidation succeeded', ['key' => $key]);
        } catch (\Throwable $e) {
            $this->profiler->fail($class, $e);
            $this->logger->warning('Background revalidation failed', [
                'key' => $key,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function revalidateWithLock(CacheableViewModel $viewModel, string $key): void
    {
        try {
            $this->revalidate($viewModel, $key);
        } finally {
            $this->revalidationLock?->release($key);
        }
    }

    private function fetchAndStore(
        CacheableViewModel $viewModel,
        string $key,
        ?CacheEntry $existingEntry,
    ): object {
        $viewContext = $this->contextResolver->getViewContext();
        $requestContext = $this->contextResolver->getRequestContext();
        $class = $viewModel::class;

        $dependencies = $viewModel instanceof WithDependencies
            ? $viewModel->getDependencies()
            : [];

        $this->profiler->start($class, $viewContext, $requestContext, $dependencies);

        try {
            $future = $viewModel->resolve($viewContext, $requestContext);
            $value = $future->await();
            $this->profiler->finish($class, $value);
            $this->store($viewModel, $key, $value);
            return $value;
        } catch (\Throwable $e) {
            $this->profiler->fail($class, $e);
            if ($existingEntry?->isStaleServableOnError()) {
                $now = $this->epoch->getElapsed();
                $this->logger->warning('Serving stale due to error', [
                    'key' => $key,
                    'exception' => $e->getMessage(),
                ]);
                // Duration is 0 as the actual resolution time is captured in the failed entry
                $this->profiler->recordCacheHit($class, 'stale_error', $now, $now);
                return $existingEntry->value;
            }
            throw $e;
        }
    }

    private function store(CacheableViewModel $viewModel, string $key, object $value): void
    {
        $viewContext = $this->contextResolver->getViewContext();
        $requestContext = $this->contextResolver->getRequestContext();

        $entry = new CacheEntry(
            value: $value,
            createdAt: time(),
            maxAge: $viewModel->getMaxAge(),
            staleWhileRevalidate: $viewModel->getStaleWhileRevalidate(),
            staleIfError: $viewModel->getStaleIfError(),
        );

        $this->cache->set($key, $entry, $viewModel->getCacheTags($viewContext, $requestContext));
    }
}
