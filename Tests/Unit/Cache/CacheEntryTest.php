<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit\Cache;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Cache\CacheEntry;

/** Tests for CacheEntry */
final class CacheEntryTest extends TestCase
{
    public function testAgeCalculation(): void
    {
        $createdAt = 1000;
        $entry = new CacheEntry('value', $createdAt, 300, 3600, 86400);

        static::assertSame(0, $entry->age(1000));
        static::assertSame(100, $entry->age(1100));
        static::assertSame(500, $entry->age(1500));
    }

    #[DataProvider('freshnessProvider')]
    public function testIsFresh(int $age, bool $expected): void
    {
        $createdAt = 1000;
        $maxAge = 300;
        $entry = new CacheEntry('value', $createdAt, $maxAge, 3600, 86400);

        static::assertSame($expected, $entry->isFresh($createdAt + $age));
    }

    public static function freshnessProvider(): array
    {
        return [
            'at creation' => [0, true],
            'mid fresh window' => [150, true],
            'at boundary' => [300, true],
            'just past boundary' => [301, false],
            'in stale window' => [1000, false],
        ];
    }

    #[DataProvider('staleRevalidatableProvider')]
    public function testIsStaleRevalidatable(int $age, bool $expected): void
    {
        $createdAt = 1000;
        $entry = new CacheEntry('value', $createdAt, 300, 3600, 86400);

        static::assertSame($expected, $entry->isStaleRevalidatable($createdAt + $age));
    }

    public static function staleRevalidatableProvider(): array
    {
        return [
            'fresh' => [100, true],
            'at maxAge boundary' => [300, true],
            'in stale window' => [1000, true],
            'at swr boundary' => [3900, true],
            'past swr boundary' => [3901, false],
        ];
    }

    #[DataProvider('staleServableOnErrorProvider')]
    public function testIsStaleServableOnError(int $age, bool $expected): void
    {
        $createdAt = 1000;
        $entry = new CacheEntry('value', $createdAt, 300, 3600, 86400);

        static::assertSame($expected, $entry->isStaleServableOnError($createdAt + $age));
    }

    public static function staleServableOnErrorProvider(): array
    {
        return [
            'fresh' => [100, true],
            'in stale window' => [1000, true],
            'past swr but in error window' => [5000, true],
            'at error boundary' => [86700, true],
            'past error boundary' => [86701, false],
        ];
    }

    public function testGetTotalTtl(): void
    {
        // staleWhileRevalidate > staleIfError
        $entry1 = new CacheEntry('value', 1000, 300, 3600, 1800);
        static::assertSame(3900, $entry1->getTotalTtl());

        // staleIfError > staleWhileRevalidate
        $entry2 = new CacheEntry('value', 1000, 300, 3600, 86400);
        static::assertSame(86700, $entry2->getTotalTtl());

        // Equal
        $entry3 = new CacheEntry('value', 1000, 300, 3600, 3600);
        static::assertSame(3900, $entry3->getTotalTtl());
    }

    public function testValueIsPreserved(): void
    {
        $dto = new \stdClass();
        $dto->name = 'test';

        $entry = new CacheEntry($dto, time(), 300, 3600, 86400);

        static::assertSame($dto, $entry->value);
        static::assertSame('test', $entry->value->name);
    }

    public function testDisabledSwrWindow(): void
    {
        $createdAt = 1000;
        $entry = new CacheEntry('value', $createdAt, 300, 0, 0);

        // Fresh
        static::assertTrue($entry->isFresh($createdAt + 300));
        static::assertTrue($entry->isStaleRevalidatable($createdAt + 300));
        static::assertTrue($entry->isStaleServableOnError($createdAt + 300));

        // Past maxAge - all false since swr=0 and error=0
        static::assertFalse($entry->isFresh($createdAt + 301));
        static::assertFalse($entry->isStaleRevalidatable($createdAt + 301));
        static::assertFalse($entry->isStaleServableOnError($createdAt + 301));
    }
}
