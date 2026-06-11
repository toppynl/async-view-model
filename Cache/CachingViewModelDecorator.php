<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Cache;

use Amp\Future;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Toppy\AsyncViewModel\Context\ContextResolverInterface;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;
use Toppy\AsyncViewModel\Profiler\TimeEpoch;
use Toppy\AsyncViewModel\Profiler\ViewModelProfilerInterface;
use Toppy\AsyncViewModel\ResetInterface;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\AsyncViewModel\WithDependencies;

/**
 * Decorator that adds SWR caching to ViewModelManager.
 *
 * Only caches ViewModels implementing CacheableViewModel.
 * Non-cacheable ViewModels pass through unchanged.
 */
// @mago-ignore analysis:invalid-return-statement,mixed-return-statement - PSR Container::get() returns mixed; vendor limitation
final class CachingViewModelDecorator implements ViewModelManagerInterface, ResetInterface
{
    /**
     * In-flight resolutions for CacheableViewModels started by preload().
     *
     * get() awaits these instead of resolving a second time, so a preloaded
     * cacheable view model is fetched exactly once. Every stored Future is
     * ignore()d, so it can never surface as an UnhandledFutureError even when
     * get() is never called for it.
     *
     * @var array<class-string, Future<object>>
     */
    private array $inflight = [];

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

    #[\Override]
    public function preload(string $class): void
    {
        $viewModel = $this->cacheableViewModel($class);

        // Non-cacheable view models keep their pending Future on the inner manager,
        // which get() consumes. Cacheable ones are resolved through this decorator's
        // cache, so delegating their preload to inner would orphan that Future.
        if ($viewModel === null) {
            $this->inner->preload($class);
            return;
        }

        $this->startInflight($class, $viewModel);
    }

    #[\Override]
    public function preloadAll(array $classes): void
    {
        $deferred = [];

        foreach ($classes as $class) {
            $viewModel = $this->cacheableViewModel($class);
            if ($viewModel === null) {
                $deferred[] = $class;
                continue;
            }

            $this->startInflight($class, $viewModel);
        }

        if ($deferred !== []) {
            $this->inner->preloadAll($deferred);
        }
    }

    #[\Override]
    public function preloadWithFuture(string $class): Future
    {
        $viewModel = $this->cacheableViewModel($class);
        if ($viewModel === null) {
            return $this->inner->preloadWithFuture($class);
        }

        // Either an in-flight fetch, or null when the cache can already serve it.
        // @mago-ignore analysis:possibly-invalid-argument - Generic type variance issue; $class is validated at runtime
        return $this->startInflight($class, $viewModel) ?? Future::complete($this->get($class));
    }

    #[\Override]
    public function all(): array
    {
        return [...$this->inflight, ...$this->inner->all()];
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface When container cannot fetch the service
     * @throws \Psr\Container\NotFoundExceptionInterface When the service is not found in container
     * @throws \Throwable When the view model resolution fails and no stale entry is available
     */
    #[\Override]
    public function get(string $class): object
    {
        $viewModel = $this->cacheableViewModel($class);

        if ($viewModel === null) {
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
                $this->triggerRevalidation($viewModel, $key, $viewContext, $requestContext);
                $this->profiler->recordCacheHit($class, 'stale', $startTime, $endTime);
                return $entry->value;
            }
        }

        $this->logger->debug('Cache miss', ['key' => $key]);
        return $this->fetchAndStore($class, $viewModel, $key, $entry, $viewContext, $requestContext);
    }

    #[\Override]
    public function reset(): void
    {
        $this->inflight = [];
    }

    /**
     * The revalidation fiber may only execute after the triggering request has
     * finished (the worker's event loop outlives requests), when the context
     * resolver already holds the NEXT request's contexts. The contexts that
     * produced $key are therefore captured here and passed along; re-reading
     * the resolver inside the fiber would store another request's data under
     * this key.
     */
    private function triggerRevalidation(
        CacheableViewModel $viewModel,
        string $key,
        ViewContext $viewContext,
        RequestContext $requestContext,
    ): void {
        // If no lock configured, always revalidate (backward compatible)
        if ($this->revalidationLock === null) {
            $this->logger->debug('Cache hit (stale), revalidating', ['key' => $key]);
            \Amp\async(fn() => $this->revalidate($viewModel, $key, $viewContext, $requestContext));
            return;
        }

        // Try to acquire lock - only one request should revalidate
        if (!$this->revalidationLock->acquire($key)) {
            $this->logger->debug('Cache hit (stale), revalidation already in progress', ['key' => $key]);
            return;
        }

        $this->logger->debug('Cache hit (stale), revalidating with lock', ['key' => $key]);
        \Amp\async(fn() => $this->revalidateWithLock($viewModel, $key, $viewContext, $requestContext));
    }

    private function revalidate(
        CacheableViewModel $viewModel,
        string $key,
        ViewContext $viewContext,
        RequestContext $requestContext,
    ): void {
        $class = $viewModel::class;

        $dependencies = $viewModel instanceof WithDependencies ? $viewModel->getDependencies() : [];

        $this->profiler->start($class, $viewContext, $requestContext, $dependencies);

        try {
            $future = $viewModel->resolve($viewContext, $requestContext);
            $value = $future->await();
            $this->profiler->finish($class, $value);
            $this->store($viewModel, $key, $value, $viewContext, $requestContext);
            $this->logger->debug('Background revalidation succeeded', ['key' => $key]);
        } catch (\Throwable $e) {
            $this->profiler->fail($class, $e);
            $this->logger->warning('Background revalidation failed', [
                'key' => $key,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function revalidateWithLock(
        CacheableViewModel $viewModel,
        string $key,
        ViewContext $viewContext,
        RequestContext $requestContext,
    ): void {
        try {
            $this->revalidate($viewModel, $key, $viewContext, $requestContext);
        } finally {
            $this->revalidationLock?->release($key);
        }
    }

    /**
     * @throws \Throwable When the view model resolution fails and no stale entry is available
     */
    private function fetchAndStore(
        string $class,
        CacheableViewModel $viewModel,
        string $key,
        ?CacheEntry $existingEntry,
        ViewContext $viewContext,
        RequestContext $requestContext,
    ): object {
        try {
            $future = $this->takeInflight($class, $viewModel, $viewContext, $requestContext);
            $value = $future->await();
            $this->profiler->finish($class, $value);
            $this->store($viewModel, $key, $value, $viewContext, $requestContext);
            return $value;
        } catch (\Throwable $e) {
            unset($this->inflight[$class]);
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

    /**
     * Resolve the view model from the container, returning it only when it is a
     * CacheableViewModel this decorator should handle; null otherwise (the call is
     * then delegated to the inner manager unchanged).
     */
    private function cacheableViewModel(string $class): ?CacheableViewModel
    {
        if (!$this->viewModels->has($class)) {
            return null;
        }

        $viewModel = $this->viewModels->get($class);

        return $viewModel instanceof CacheableViewModel ? $viewModel : null;
    }

    /**
     * Start (or reuse) the in-flight resolution for a cacheable view model so the
     * request runs in parallel with the rest of the response, unless the cache can
     * already serve it.
     *
     * @return Future<object>|null The in-flight Future, or null when a cached entry
     *                             is available and get() will serve it without resolving.
     */
    private function startInflight(string $class, CacheableViewModel $viewModel): ?Future
    {
        if (isset($this->inflight[$class])) {
            return $this->inflight[$class];
        }

        $viewContext = $this->contextResolver->getViewContext();
        $requestContext = $this->contextResolver->getRequestContext();

        $entry = $this->cache->get($viewModel->getCacheKey($viewContext, $requestContext));

        // get() will serve fresh entries directly and stale-but-revalidatable entries
        // while revalidating in the background, so no early fetch is needed for those.
        if ($entry instanceof CacheEntry && ($entry->isFresh() || $entry->isStaleRevalidatable())) {
            return null;
        }

        $dependencies = $viewModel instanceof WithDependencies ? $viewModel->getDependencies() : [];
        $this->profiler->start($class, $viewContext, $requestContext, $dependencies);

        // ignore() so a rejected Future is never forwarded to the event loop as an
        // UnhandledFutureError if get() is never called for this class (e.g. the
        // template branch that needs it is not rendered). fetchAndStore() awaits the
        // very same Future, where the error is caught and handled normally.
        $future = $viewModel->resolve($viewContext, $requestContext);
        $future->ignore();
        $this->inflight[$class] = $future;

        return $future;
    }

    /**
     * Take the in-flight Future started by preload(), or resolve now when the view
     * model was not preloaded (mirroring the original, non-preloaded behaviour).
     *
     * @return Future<object>
     */
    private function takeInflight(
        string $class,
        CacheableViewModel $viewModel,
        ViewContext $viewContext,
        RequestContext $requestContext,
    ): Future {
        if (isset($this->inflight[$class])) {
            $future = $this->inflight[$class];
            unset($this->inflight[$class]);

            return $future;
        }

        $dependencies = $viewModel instanceof WithDependencies ? $viewModel->getDependencies() : [];

        // Profiler is started here (not preloaded) so its timing covers the resolution.
        $this->profiler->start($class, $viewContext, $requestContext, $dependencies);

        return $viewModel->resolve($viewContext, $requestContext);
    }

    private function store(
        CacheableViewModel $viewModel,
        string $key,
        object $value,
        ViewContext $viewContext,
        RequestContext $requestContext,
    ): void {
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
