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

    public function testCachePolicyDefaultsToNull(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent');

        static::assertNull($exception->ttl);
        static::assertNull($exception->staleWhileRevalidate);
        static::assertNull($exception->staleIfError);
    }

    public function testCachePolicyIsSetFromNamedArguments(): void
    {
        $exception = new NoDataException(
            'App\\ViewModel\\CmsContent',
            ttl: 300,
            staleWhileRevalidate: 600,
            staleIfError: 900,
        );

        static::assertSame('App\\ViewModel\\CmsContent', $exception->viewModelClass);
        static::assertSame('ViewModel resolved with no data', $exception->getMessage());
        static::assertSame(300, $exception->ttl);
        static::assertSame(600, $exception->staleWhileRevalidate);
        static::assertSame(900, $exception->staleIfError);
    }
}
