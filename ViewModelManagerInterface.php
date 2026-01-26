<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

use Amp\Future;

interface ViewModelManagerInterface
{
    /**
     * Start async fetching for a view model (non-blocking).
     *
     * @param class-string<AsyncViewModel> $class
     */
    public function preload(string $class): void;

    /**
     * Bulk preload multiple view models.
     *
     * @param array<class-string<AsyncViewModel>> $classes
     */
    public function preloadAll(array $classes): void;

    /**
     * Get resolved data, blocking if necessary.
     *
     * Returns a lazy proxy that awaits the Future on first property access.
     *
     * @template T of object
     * @param class-string<AsyncViewModel<T>> $class
     * @return T
     */
    public function get(string $class): object;

    /**
     * Start async fetching and return the Future.
     *
     * @param class-string<AsyncViewModel> $class
     * @return Future<object>
     */
    public function preloadWithFuture(string $class): Future;

    /**
     * Get all pending futures and resolved view models.
     *
     * Returns a combined array of all ViewModels being tracked, whether still
     * pending (as Futures) or already resolved (as data objects/lazy proxies).
     *
     * @return array<class-string, Future<object>|object>
     */
    public function all(): array;
}
