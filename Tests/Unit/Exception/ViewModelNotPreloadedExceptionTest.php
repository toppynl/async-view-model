<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Exception\ViewModelNotPreloadedException;

/** Tests for ViewModelNotPreloadedException */
final class ViewModelNotPreloadedExceptionTest extends TestCase
{
    public function testExceptionContainsViewModelClass(): void
    {
        $exception = new ViewModelNotPreloadedException('App\\ViewModel\\Stock');

        static::assertSame('App\\ViewModel\\Stock', $exception->viewModelClass);
        static::assertStringContainsString('App\\ViewModel\\Stock', $exception->getMessage());
        static::assertStringContainsString('preload', $exception->getMessage());
    }

    public function testExtendsLogicException(): void
    {
        $exception = new ViewModelNotPreloadedException('App\\ViewModel\\Stock');

        static::assertInstanceOf(\LogicException::class, $exception);
    }
}
