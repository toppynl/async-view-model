<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Exception;

/**
 * Thrown when a ViewModel resolves successfully but has no data to return.
 *
 * This is NOT an error - it's a valid "empty" state (e.g., product without CMS content).
 * Distinct from ViewModelResolutionException which indicates actual failures.
 */
final class NoDataException extends \RuntimeException
{
    public function __construct(
        public readonly string $viewModelClass,
        string $message = 'ViewModel resolved with no data',
    ) {
        parent::__construct($message);
    }
}
