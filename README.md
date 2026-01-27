# Async View Model

> **Read-Only Repository**
> This is a read-only subtree split from the main repository.
> Please submit issues and pull requests to [toppynl/symfony-astro](https://github.com/toppynl/symfony-astro).

Framework-agnostic async view model resolution with AmPHP Fibers. This is Layer 0 (core) of the Toppy Stack - a foundation for parallel data fetching that integrates with any PHP framework supporting PSR containers.

## Installation

```bash
composer require toppy/async-view-model
```

## Requirements

- PHP 8.4+
- [amphp/amp](https://github.com/amphp/amp) ^3.0 - Fiber-based async primitives
- [amphp/http-client](https://github.com/amphp/http-client) ^5.0 - Async HTTP client
- [psr/container](https://github.com/php-fig/container) ^1.1 || ^2.0 - Service container interface
- [psr/log](https://github.com/php-fig/log) ^1.0 || ^2.0 || ^3.0 - Logging interface

## Quick Start

```php
use Amp\Future;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

// 1. Define your data class
final readonly class ProductStock
{
    public function __construct(
        public int $quantity,
        public bool $inStock,
    ) {}
}

// 2. Implement AsyncViewModel
final class ProductStockViewModel implements AsyncViewModel
{
    public function __construct(
        private readonly StockApiClient $api,
    ) {}

    /**
     * @return Future<ProductStock>
     */
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        $productId = $requestContext->get('productId');

        // Returns immediately - actual HTTP request runs in a Fiber
        return $this->api->getStockAsync($productId);
    }
}

// 3. Resolve via ViewModelManager
$manager->preload(ProductStockViewModel::class);
// ... render template shell ...
$stock = $manager->get(ProductStockViewModel::class); // Blocks only when data is accessed
```

## Architecture

### Key Classes

| Class | Purpose |
|-------|---------|
| `AsyncViewModel` | Core interface - implementations return `Future<T>` from `resolve()` |
| `ViewModelManager` | Orchestrates preloading, dependency ordering, and lazy proxy creation |
| `ViewContext` | Immutable user/session state (currency, locale, B2B flag) - safe for Fibers |
| `RequestContext` | Immutable route parameters with polymorphic `fromArray()` deserialization |
| `WithDependencies` | Interface for ViewModels that depend on other ViewModels |
| `DependencyGraph` | DAG-based topological sorting with priority by dependent count |
| `CacheableViewModel` | Interface for SWR caching with TTL semantics |
| `ResetInterface` | Worker mode support - reset state between requests |
| `ViewModelProfilerInterface` | Timing and parallel efficiency metrics collection |

### Directory Structure

```
Toppy/Component/AsyncViewModel/
├── AsyncViewModel.php              # Core interface
├── AsyncIslandProviderInterface.php # Island provider contract
├── ViewModelManager.php            # Resolution orchestrator
├── ViewModelManagerInterface.php   # Manager contract
├── WithDependencies.php            # Dependency declaration interface
├── DependencyGraph.php             # Topological sort implementation
├── ResetInterface.php              # Worker mode reset contract
├── WithCacheMetadata.php           # Cache metadata interface
├── CacheMetadataBehaviour.php      # Cache metadata trait
├── Context/
│   ├── ViewContext.php             # Immutable user/session state
│   ├── RequestContext.php          # Immutable route parameters
│   ├── ContextFactoryInterface.php # Context creation contract
│   └── ContextResolverInterface.php # Context resolution contract
├── Cache/
│   ├── CacheableViewModel.php      # SWR caching interface
│   ├── CacheEntry.php              # Cache entry value object
│   ├── CachingViewModelDecorator.php # Caching decorator
│   ├── SwrCacheInterface.php       # Stale-while-revalidate cache contract
│   └── RevalidationLockInterface.php # Distributed lock for revalidation
├── Exception/
│   ├── NoDataException.php         # Data not available
│   ├── ViewModelNotPreloadedException.php # Preload required error
│   └── ViewModelResolutionException.php # Resolution failure
├── Profiler/
│   ├── ViewModelProfilerInterface.php # Profiler contract
│   ├── NullViewModelProfiler.php   # No-op implementation for production
│   ├── TimeEpoch.php               # Shared time reference
│   ├── TimelineEntry.php           # Resolution timing data
│   ├── HttpClientProfilerInterface.php # HTTP profiler contract
│   ├── NullHttpClientProfiler.php  # No-op HTTP profiler
│   └── HttpRequestEntry.php        # HTTP request timing data
├── Http/
│   └── ProfilingApplicationInterceptor.php # AmPHP HTTP client interceptor
├── Tests/
│   ├── Unit/                       # Unit tests
│   └── Fixtures/                   # Test doubles
└── composer.json
```

## Usage

### Creating a View Model

View models implement `AsyncViewModel` and return a `Future<T>` from `resolve()`. The PHPDoc `@return Future<DataClass>` is required for lazy proxy creation.

```php
use Amp\Future;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\AsyncViewModel\Context\ViewContext;

final readonly class UserProfileData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $avatarUrl,
    ) {}
}

/**
 * @implements AsyncViewModel<UserProfileData>
 */
final class UserProfileViewModel implements AsyncViewModel
{
    public function __construct(
        private readonly UserApiClient $api,
    ) {}

    /**
     * @return Future<UserProfileData>
     */
    #[\Override]
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        $userId = $requestContext->get('userId');

        // Non-blocking - starts HTTP request in a Fiber
        return $this->api->fetchUserAsync($userId)->map(
            fn(array $data) => new UserProfileData(
                name: $data['name'],
                email: $data['email'],
                avatarUrl: $data['avatar_url'],
            )
        );
    }
}
```

### Context Objects

Both context objects are **immutable** and safe to pass to background Fibers.

#### ViewContext - User/Session State

```php
use Toppy\AsyncViewModel\Context\ViewContext;

// Create from session/request data
$viewContext = ViewContext::create(
    currency: 'EUR',
    locale: 'en_GB',
    isB2B: false,
    isVatExempt: false,
    customerGroup: 'retail',
    isPrivate: false, // Whether response is cacheable
);

// Access in ViewModel
public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
{
    $currency = $viewContext->getCurrency(); // 'EUR'
    $locale = $viewContext->getLocale();     // 'en_GB'

    if ($viewContext->isB2B()) {
        // B2B-specific logic
    }
}
```

#### RequestContext - Route Parameters

```php
use Toppy\AsyncViewModel\Context\RequestContext;

// Create from route parameters
$requestContext = RequestContext::create(
    params: ['productId' => 123, 'categorySlug' => 'electronics'],
    requestId: 'req_abc123',
);

// Access in ViewModel
public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
{
    $productId = $requestContext->get('productId');     // 123
    $category = $requestContext->get('categorySlug');   // 'electronics'
    $missing = $requestContext->get('foo', 'default');  // 'default'
    $all = $requestContext->all();                      // Full params array
}

// Serialization for encrypted URL transport
$serialized = $requestContext->toArray();
// ['_type' => 'Toppy\AsyncViewModel\Context\RequestContext', 'params' => [...], 'requestId' => '...']

$restored = RequestContext::fromArray($serialized);
```

### Resolution with ViewModelManager

The `ViewModelManager` orchestrates async resolution with these key features:

1. **Non-blocking preload** - Starts Futures immediately, doesn't wait
2. **Dependency ordering** - ViewModels with the most dependents start first
3. **Lazy proxies** - `get()` returns a proxy that blocks only on first property access
4. **Deduplication** - Same class preloaded twice returns same Future

```php
use Toppy\AsyncViewModel\ViewModelManager;
use Toppy\AsyncViewModel\Profiler\NullViewModelProfiler;

// Setup (typically done by DI container)
$manager = new ViewModelManager(
    viewModels: $container,  // PSR ContainerInterface with registered ViewModels
    profiler: new NullViewModelProfiler(),
    contextResolver: $contextResolver,
);

// Preload single ViewModel (non-blocking)
$manager->preload(ProductStockViewModel::class);

// Preload multiple ViewModels with automatic dependency discovery
$manager->preloadAll([
    ProductDetailsViewModel::class,
    ProductReviewsViewModel::class,
    RelatedProductsViewModel::class,
]);

// Get data - returns lazy proxy, blocks only on property access
$stock = $manager->get(ProductStockViewModel::class);
echo $stock->quantity; // <-- Fiber awaited here

// Get Future directly for manual control
$future = $manager->preloadWithFuture(ProductStockViewModel::class);
$data = $future->await(); // Explicit blocking

// Inspect all tracked ViewModels
$all = $manager->all(); // Returns array of Futures and resolved objects
```

### Declaring Dependencies

When ViewModels depend on data from other ViewModels, implement `WithDependencies`:

```php
use Toppy\AsyncViewModel\WithDependencies;

final class ProductPageViewModel implements AsyncViewModel, WithDependencies
{
    public function __construct(
        private readonly ViewModelManagerInterface $manager,
    ) {}

    /**
     * @return array<class-string<AsyncViewModel>>
     */
    #[\Override]
    public function getDependencies(): array
    {
        return [
            ProductDetailsViewModel::class,
            ProductStockViewModel::class,
        ];
    }

    /**
     * @return Future<ProductPageData>
     */
    #[\Override]
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        // Dependencies are guaranteed to have started before this ViewModel
        $detailsFuture = $this->manager->preloadWithFuture(ProductDetailsViewModel::class);
        $stockFuture = $this->manager->preloadWithFuture(ProductStockViewModel::class);

        return Future\all([$detailsFuture, $stockFuture])->map(
            fn(array $results) => new ProductPageData(
                details: $results[0],
                stock: $results[1],
            )
        );
    }
}
```

The `DependencyGraph` performs topological sorting to ensure:
- Dependencies start before dependents
- Circular dependencies are detected with clear error messages
- ViewModels with the most transitive dependents start first (maximizes parallelism)

### SWR Caching

Implement `CacheableViewModel` for stale-while-revalidate caching:

```php
use Toppy\AsyncViewModel\Cache\CacheableViewModel;

final class ProductStockViewModel implements CacheableViewModel
{
    /**
     * @return Future<ProductStock>
     */
    #[\Override]
    public function resolve(ViewContext $viewContext, RequestContext $requestContext): Future
    {
        return $this->api->getStockAsync($requestContext->get('productId'));
    }

    #[\Override]
    public function getCacheKey(ViewContext $viewContext, RequestContext $requestContext): string
    {
        return sprintf('stock_%d_%s', $requestContext->get('productId'), $viewContext->getCurrency());
    }

    #[\Override]
    public function getCacheTags(ViewContext $viewContext, RequestContext $requestContext): array
    {
        return ['product_' . $requestContext->get('productId'), 'stock'];
    }

    #[\Override]
    public function getMaxAge(): int
    {
        return 60; // Fresh for 60 seconds
    }

    #[\Override]
    public function getStaleWhileRevalidate(): int
    {
        return 300; // Serve stale for 5 minutes while revalidating async
    }

    #[\Override]
    public function getStaleIfError(): int
    {
        return 3600; // Serve stale for 1 hour if revalidation fails
    }
}
```

### Worker Mode Considerations

In worker mode (FrankenPHP, RoadRunner), PHP processes persist across requests. Services holding request-scoped state must implement `ResetInterface`:

```php
use Toppy\AsyncViewModel\ResetInterface;

final class ViewModelManager implements ResetInterface
{
    private array $futures = [];
    private array $resolved = [];

    #[\Override]
    public function reset(): void
    {
        $this->futures = [];
        $this->resolved = [];
    }
}
```

When using Symfony, services can implement both this package's `ResetInterface` and Symfony's `Symfony\Contracts\Service\ResetInterface` for automatic reset handling.

### Profiling

Implement `ViewModelProfilerInterface` to collect timing data:

```php
use Toppy\AsyncViewModel\Profiler\ViewModelProfilerInterface;
use Toppy\AsyncViewModel\Profiler\TimelineEntry;

// Get profiler entries after resolution
$entries = $profiler->getEntries();

foreach ($entries as $entry) {
    echo sprintf(
        "%s: %.2fms (status: %s)\n",
        $entry->getShortName(),
        $entry->getDuration(),
        $entry->status,
    );
}

// Check parallel efficiency (1.0 = perfect parallelism)
$efficiency = $profiler->getParallelEfficiency();
echo "Parallel efficiency: " . ($efficiency * 100) . "%\n";

// Total wall-clock time
echo "Total time: " . $profiler->getTotalTime() . "ms\n";
```

Use `NullViewModelProfiler` in production to avoid profiling overhead.

## Integration

This package is Layer 0 (core) of the Toppy Stack - it has no framework dependencies and can be used standalone or as a foundation for framework-specific integrations.

```
symfony-async-twig-bundle (Layer 3: Symfony bridge)
        │
   ┌────┴────┐
   ▼         ▼
twig-prerender (Layer 2) ──► twig-streaming (Layer 1)
                                   │
                                   │    twig-view-model (Layer 1)
                                   │         │
                                   └────┬────┘
                                        ▼
                              async-view-model (Layer 0: core) ◄── You are here
```

### Framework Integrations

| Package | Purpose |
|---------|---------|
| `toppy/twig-view-model` | Twig `view()` function integration |
| `toppy/twig-streaming` | Streaming response with deferred slots |
| `toppy/twig-prerender` | Twig `{% include %}` modifiers |
| `toppy/symfony-async-twig-bundle` | Full Symfony integration |

## Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run single test file
./vendor/bin/phpunit Tests/Unit/ViewModelManagerTest.php

# Run single test method
./vendor/bin/phpunit --filter testPreloadAllStartsDependenciesFirst
```

### Writing Tests

Use the `NullViewModelProfiler` and stub containers:

```php
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Toppy\AsyncViewModel\Profiler\NullViewModelProfiler;
use Toppy\AsyncViewModel\ViewModelManager;

final class MyViewModelTest extends TestCase
{
    public function testResolution(): void
    {
        $viewModel = new MyViewModel(/* dependencies */);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($viewModel);

        $contextResolver = $this->createContextResolver();
        $manager = new ViewModelManager($container, new NullViewModelProfiler(), $contextResolver);

        $manager->preload(MyViewModel::class);
        $result = $manager->get(MyViewModel::class);

        static::assertInstanceOf(MyData::class, $result);
    }
}
```

## License

Proprietary - see LICENSE file for details.
