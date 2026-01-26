<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Exception\NoDataException;

/** Tests for NoDataException */
final class NoDataExceptionTest extends TestCase
{
    public function testExceptionContainsViewModelClass(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent');

        static::assertSame('App\\ViewModel\\CmsContent', $exception->viewModelClass);
    }

    public function testDefaultMessage(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent');

        static::assertSame('ViewModel resolved with no data', $exception->getMessage());
    }

    public function testCustomMessage(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent', 'Product has no CMS content');

        static::assertSame('Product has no CMS content', $exception->getMessage());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent');

        static::assertInstanceOf(\RuntimeException::class, $exception);
    }
}
