<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Exception\ViewModelNotPreloadedException;

final class ViewModelNotPreloadedExceptionTest extends TestCase
{
    public function testExceptionContainsViewModelClass(): void
    {
        $exception = new ViewModelNotPreloadedException('App\\ViewModel\\Stock');

        $this->assertSame('App\\ViewModel\\Stock', $exception->viewModelClass);
        $this->assertStringContainsString('App\\ViewModel\\Stock', $exception->getMessage());
        $this->assertStringContainsString('preload', $exception->getMessage());
    }

    public function testExtendsLogicException(): void
    {
        $exception = new ViewModelNotPreloadedException('App\\ViewModel\\Stock');

        $this->assertInstanceOf(\LogicException::class, $exception);
    }
}
