<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Exception\NoDataException;

final class NoDataExceptionTest extends TestCase
{
    public function testExceptionContainsViewModelClass(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent');

        $this->assertSame('App\\ViewModel\\CmsContent', $exception->viewModelClass);
    }

    public function testDefaultMessage(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent');

        $this->assertSame('ViewModel resolved with no data', $exception->getMessage());
    }

    public function testCustomMessage(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent', 'Product has no CMS content');

        $this->assertSame('Product has no CMS content', $exception->getMessage());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new NoDataException('App\\ViewModel\\CmsContent');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
