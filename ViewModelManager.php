<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

use Amp\Future;
use Psr\Container\ContainerInterface;
use Toppy\AsyncViewModel\Context\ContextResolverInterface;
use Toppy\AsyncViewModel\Exception\ViewModelResolutionException;
use Toppy\AsyncViewModel\Profiler\ViewModelProfilerInterface;

// @mago-ignore analysis:invalid-return-statement - PSR Container::get() returns mixed; cannot be narrowed without runtime assertions
final class ViewModelManager implements ViewModelManagerInterface, ResetInterface
{
    /** @var array<class-string, Future<object>> */
    private array $futures = [];

    /** @var array<class-string, object> */
    private array $resolved = [];

    public function __construct(
        private readonly ContainerInterface $viewModels,
        private readonly ViewModelProfilerInterface $profiler,
        private readonly ContextResolverInterface $contextResolver,
    ) {}

    /**
     * @throws \InvalidArgumentException When the view model is not registered
     * @throws \Psr\Container\ContainerExceptionInterface When container cannot fetch the service
     * @throws \Psr\Container\NotFoundExceptionInterface When the service is not found in container
     */
    #[\Override]
    public function preload(string $class): void
    {
        if (isset($this->futures[$class]) || isset($this->resolved[$class])) {
            return;
        }

        if (!$this->viewModels->has($class)) {
            throw new \InvalidArgumentException(sprintf('View model "%s" is not registered.', $class));
        }

        /** @var AsyncViewModel $viewModel */
        $viewModel = $this->viewModels->get($class);

        $this->startFuture($class, $viewModel);
    }

    /**
     * @throws \InvalidArgumentException When a view model is not registered
     * @throws \LogicException When circular dependencies are detected
     * @throws \Psr\Container\ContainerExceptionInterface When container cannot fetch a service
     * @throws \Psr\Container\NotFoundExceptionInterface When a service is not found in container
     */
    #[\Override]
    public function preloadAll(array $classes): void
    {
        if ($classes === []) {
            return;
        }

        // Phase 1: Discovery - collect all ViewModels including transitive dependencies
        $instances = [];
        $graph = new DependencyGraph();

        $discovered = $this->discoverAll($classes);

        foreach ($discovered as $class) {
            if (isset($this->futures[$class]) || isset($this->resolved[$class])) {
                continue;
            }

            /** @var AsyncViewModel $viewModel */
            $viewModel = $this->viewModels->get($class);
            $instances[$class] = $viewModel;

            $deps = $viewModel instanceof WithDependencies ? array_values($viewModel->getDependencies()) : [];

            $graph->addNode($class, $deps);
        }

        // Phase 2: Detect cycles (fail fast)
        $graph->detectCycle();

        // Phase 3: Start ALL futures in priority order (most dependents first)
        foreach ($graph->getStartOrder() as $class) {
            if (isset($instances[$class])) {
                $this->startFuture($class, $instances[$class]);
            }
        }
    }

    /**
     * @throws Exception\ViewModelNotPreloadedException When the view model was not preloaded
     * @throws Exception\ViewModelResolutionException When the view model fails to resolve
     * @throws \ReflectionException When reflection fails on the data class
     * @throws \RuntimeException When the ViewModel PHPDoc is missing or invalid
     */
    #[\Override]
    public function get(string $class): object
    {
        if (isset($this->resolved[$class])) {
            return $this->resolved[$class];
        }

        if (!isset($this->futures[$class])) {
            throw new Exception\ViewModelNotPreloadedException($class);
        }

        $future = $this->futures[$class];
        $dataClass = $this->resolveDataClass($class);

        try {
            $proxy = $this->createLazyProxy($dataClass, $future, $class);
            $this->resolved[$class] = $proxy;
            unset($this->futures[$class]);

            return $proxy;
        } catch (\Throwable $e) {
            throw new ViewModelResolutionException(
                viewModelClass: $class,
                message: sprintf('Failed to resolve view model "%s": %s', $class, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * @throws \InvalidArgumentException When the view model is not registered
     * @throws \Psr\Container\ContainerExceptionInterface When container cannot fetch the service
     * @throws \Psr\Container\NotFoundExceptionInterface When the service is not found in container
     */
    #[\Override]
    public function preloadWithFuture(string $class): Future
    {
        if (isset($this->resolved[$class])) {
            // Already resolved, return completed future
            return Future::complete($this->resolved[$class]);
        }

        if (!isset($this->futures[$class])) {
            $this->preload($class);
        }

        return $this->futures[$class];
    }

    #[\Override]
    public function all(): array
    {
        return [...$this->futures, ...$this->resolved];
    }

    #[\Override]
    public function reset(): void
    {
        $this->futures = [];
        $this->resolved = [];
    }

    /**
     * Recursively discover all ViewModels including transitive dependencies.
     *
     * @param array<class-string> $classes
     * @return array<class-string>
     *
     * @throws \InvalidArgumentException When a view model is not registered
     * @throws \Psr\Container\ContainerExceptionInterface When container cannot fetch a service
     * @throws \Psr\Container\NotFoundExceptionInterface When a service is not found in container
     */
    private function discoverAll(array $classes): array
    {
        $discovered = [];
        $queue = $classes;

        while ($queue !== []) {
            $class = array_shift($queue);

            if (isset($discovered[$class])) {
                continue;
            }

            if (!$this->viewModels->has($class)) {
                throw new \InvalidArgumentException(sprintf('View model "%s" is not registered.', $class));
            }

            $discovered[$class] = true;

            /** @var AsyncViewModel $viewModel */
            $viewModel = $this->viewModels->get($class);

            if ($viewModel instanceof WithDependencies) {
                foreach ($viewModel->getDependencies() as $dep) {
                    if (!isset($discovered[$dep])) {
                        $queue[] = $dep;
                    }
                }
            }
        }

        return array_keys($discovered);
    }

    /**
     * Start a Future for a ViewModel.
     */
    private function startFuture(string $class, AsyncViewModel $viewModel): void
    {
        if (isset($this->futures[$class]) || isset($this->resolved[$class])) {
            return;
        }

        $dependencies = $viewModel instanceof WithDependencies ? $viewModel->getDependencies() : [];

        $viewContext = $this->contextResolver->getViewContext();
        $requestContext = $this->contextResolver->getRequestContext();

        $this->profiler->start($class, $viewContext, $requestContext, $dependencies);

        $this->futures[$class] = $viewModel->resolve($viewContext, $requestContext);
    }

    /**
     * Create a lazy proxy that awaits the Future on first property access.
     *
     * @template T of object
     * @param class-string<T> $dataClass
     * @param Future<T> $future
     * @return T
     *
     * @throws \ReflectionException When reflection fails on the data class
     */
    private function createLazyProxy(string $dataClass, Future $future, string $viewModelClass): object
    {
        $profiler = $this->profiler;
        $reflector = new \ReflectionClass($dataClass);

        return $reflector->newLazyProxy(static function () use ($future, $viewModelClass, $profiler): object {
            try {
                $result = $future->await();
                $profiler->finish($viewModelClass, $result);
                return $result;
            } catch (\Throwable $e) {
                $profiler->fail($viewModelClass, $e);
                throw new ViewModelResolutionException(
                    viewModelClass: $viewModelClass,
                    message: sprintf('Failed to resolve view model "%s": %s', $viewModelClass, $e->getMessage()),
                    previous: $e,
                );
            }
        });
    }

    /**
     * Resolve the Data class from ViewModel's resolve() PHPDoc.
     *
     * Parses @return Future<ClassName> to extract ClassName.
     *
     * @param class-string<AsyncViewModel> $viewModelClass
     * @return class-string
     *
     * @throws \ReflectionException When reflection fails on the ViewModel class
     * @throws \RuntimeException When the ViewModel PHPDoc is missing or invalid
     */
    private function resolveDataClass(string $viewModelClass): string
    {
        $method = new \ReflectionMethod($viewModelClass, 'resolve');
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            throw new \RuntimeException(sprintf(
                'ViewModel "%s" must have PHPDoc @return Future<DataClass> on resolve()',
                $viewModelClass,
            ));
        }

        // Match @return Future<ClassName> or @return Future<Namespace\ClassName>
        $matches = [];
        if (preg_match('/@return\s+Future<([^>]+)>/', $docComment, $matches) !== 1) {
            throw new \RuntimeException(sprintf(
                'ViewModel "%s" must have @return Future<DataClass> PHPDoc on resolve()',
                $viewModelClass,
            ));
        }

        $dataClass = trim($matches[1] ?? '');

        // If already fully qualified
        if (class_exists($dataClass)) {
            return $dataClass;
        }

        // Try resolving relative to ViewModel's namespace
        $namespace = new \ReflectionClass($viewModelClass)->getNamespaceName();
        $fullyQualified = $namespace . '\\' . $dataClass;

        if (class_exists($fullyQualified)) {
            return $fullyQualified;
        }

        throw new \RuntimeException(sprintf(
            'Cannot resolve data class "%s" for ViewModel "%s"',
            $dataClass,
            $viewModelClass,
        ));
    }
}
