<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Fixtures;

/**
 * Simple data object for testing ViewModelManager::get().
 */
final class StubData
{
    public function __construct(
        public readonly string $value = 'test',
    ) {}
}
