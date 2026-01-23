<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

/**
 * View model that depends on other view models.
 *
 * Dependencies are resolved in topological order using a DAG.
 * Circular dependencies are detected at compile-time via CompilerPass.
 */
interface WithDependencies extends AsyncViewModel
{
    /**
     * Other view models this one depends on.
     *
     * Dependencies are resolved before this view model.
     * Used for ordering when multiple view models need data from each other.
     *
     * @return array<class-string<AsyncViewModel>>
     */
    public function getDependencies(): array;
}
