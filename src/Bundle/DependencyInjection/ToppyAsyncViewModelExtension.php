<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Bundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Profiler\NullViewModelProfiler;
use Toppy\AsyncViewModel\Profiler\ViewModelProfilerInterface;
use Toppy\AsyncViewModel\ViewModelManager;
use Toppy\AsyncViewModel\ViewModelManagerInterface;

final class ToppyAsyncViewModelExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Register NullViewModelProfiler as default
        $container->register(NullViewModelProfiler::class);
        $container->setAlias(ViewModelProfilerInterface::class, NullViewModelProfiler::class);

        // Auto-configure AsyncViewModel implementations with a tag
        $container->registerForAutoconfiguration(AsyncViewModel::class)
            ->addTag('toppy.async_view_model');

        // Register ViewModelManager with a service locator for tagged view models
        $container->register(ViewModelManager::class)
            ->setArgument('$viewModels', new ServiceLocatorArgument(new TaggedIteratorArgument('toppy.async_view_model', null, null, true)))
            ->setArgument('$profiler', new Reference(ViewModelProfilerInterface::class))
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->setAlias(ViewModelManagerInterface::class, ViewModelManager::class);
    }
}
