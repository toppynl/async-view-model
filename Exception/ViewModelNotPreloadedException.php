<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Exception;

/**
 * Thrown when attempting to get a ViewModel that was not preloaded.
 *
 * This is a developer error - call preload() or preloadAll() before get().
 */
final class ViewModelNotPreloadedException extends \LogicException
{
    public function __construct(
        public readonly string $viewModelClass,
    ) {
        parent::__construct(sprintf(
            'ViewModel "%s" was not preloaded. Call preload() or preloadAll() before get().',
            $viewModelClass,
        ));
    }
}
