<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\ContextResolverInterface;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;
use Toppy\AsyncViewModel\Profiler\NullViewModelProfiler;
use Toppy\AsyncViewModel\ViewModelManager;
use Toppy\AsyncViewModel\WithDependencies;

final class ViewModelManagerTest extends TestCase
{
    private array $startOrder = [];

    public function testPreloadAllStartsDependenciesFirst(): void
    {
        $this->startOrder = [];

        // Create mock ViewModels that record their start order
        $dependency = $this->createRecordingViewModel('Dependency');
        $dependent = $this->createDependentViewModel('Dependent', ['Dependency']);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(function ($class) use ($dependency, $dependent) {
            return match ($class) {
                'Dependency' => $dependency,
                'Dependent' => $dependent,
                default => throw new \InvalidArgumentException("Unknown: $class"),
            };
        });

        $contextResolver = $this->createContextResolver();
        $manager = new ViewModelManager($container, new NullViewModelProfiler(), $contextResolver);

        // Preload in wrong order - dependent first
        $manager->preloadAll(['Dependent', 'Dependency']);

        // Dependency should have started first despite being listed second
        $this->assertSame(['Dependency', 'Dependent'], $this->startOrder);
    }

    public function testPreloadAllAutoDiscoversDependencies(): void
    {
        $this->startOrder = [];

        $dependency = $this->createRecordingViewModel('Dependency');
        $dependent = $this->createDependentViewModel('Dependent', ['Dependency']);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(function ($class) use ($dependency, $dependent) {
            return match ($class) {
                'Dependency' => $dependency,
                'Dependent' => $dependent,
                default => throw new \InvalidArgumentException("Unknown: $class"),
            };
        });

        $contextResolver = $this->createContextResolver();
        $manager = new ViewModelManager($container, new NullViewModelProfiler(), $contextResolver);

        // Only preload dependent - should auto-discover Dependency
        $manager->preloadAll(['Dependent']);

        // Both should be started, Dependency first
        $this->assertSame(['Dependency', 'Dependent'], $this->startOrder);
    }

    private function createRecordingViewModel(string $name): AsyncViewModel
    {
        $startOrder = &$this->startOrder;

        return new class($name, $startOrder) implements AsyncViewModel {
            public function __construct(
                private readonly string $name,
                private array &$startOrder,
            ) {}

            /**
             * @return Future<\stdClass>
             */
            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                $this->startOrder[] = $this->name;
                return Future::complete(new \stdClass());
            }
        };
    }

    private function createDependentViewModel(string $name, array $deps): AsyncViewModel
    {
        $startOrder = &$this->startOrder;

        return new class($name, $deps, $startOrder) implements AsyncViewModel, WithDependencies {
            public function __construct(
                private readonly string $name,
                private readonly array $deps,
                private array &$startOrder,
            ) {}

            public function getDependencies(): array
            {
                return $this->deps;
            }

            /**
             * @return Future<\stdClass>
             */
            public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
            {
                $this->startOrder[] = $this->name;
                return Future::complete(new \stdClass());
            }
        };
    }

    public function testPreloadWithFutureReturnsFuture(): void
    {
        $viewModel = $this->createRecordingViewModel('Test');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $contextResolver = $this->createContextResolver();
        $manager = new ViewModelManager($container, new NullViewModelProfiler(), $contextResolver);

        $future = $manager->preloadWithFuture('Test');

        $this->assertInstanceOf(Future::class, $future);

        // Future should resolve to the data
        $result = $future->await();
        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function testPreloadWithFutureDeduplicates(): void
    {
        $this->startOrder = [];
        $viewModel = $this->createRecordingViewModel('Test');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $contextResolver = $this->createContextResolver();
        $manager = new ViewModelManager($container, new NullViewModelProfiler(), $contextResolver);

        $future1 = $manager->preloadWithFuture('Test');
        $future2 = $manager->preloadWithFuture('Test');

        // Should be the same future (deduplicated)
        $this->assertSame($future1, $future2);

        // Should only start once
        $this->assertCount(1, $this->startOrder);
    }

    private function createContextResolver(): ContextResolverInterface
    {
        $resolver = $this->createMock(ContextResolverInterface::class);
        $resolver->method('getViewContext')->willReturn(
            ViewContext::create('EUR', 'en', false, false, null)
        );
        $resolver->method('getRequestContext')->willReturn(
            RequestContext::create([], 'test')
        );
        return $resolver;
    }
}
