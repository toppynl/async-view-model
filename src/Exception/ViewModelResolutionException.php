<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Exception;

final class ViewModelResolutionException extends \RuntimeException
{
    public function __construct(
        public readonly string $viewModelClass,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
