<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\DependencyGraph;

/** Tests for DependencyGraph */
final class DependencyGraphTest extends TestCase
{
    public function testEmptyGraphReturnsEmptyOrder(): void
    {
        $graph = new DependencyGraph();

        static::assertSame([], $graph->getStartOrder());
    }

    public function testSingleNodeWithNoDependencies(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', []);

        static::assertSame(['A'], $graph->getStartOrder());
    }

    public function testNodeWithDependentsStartsFirst(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('Dependent', ['Dependency']);
        $graph->addNode('Dependency', []);

        $order = $graph->getStartOrder();

        // Dependency has a dependent, so starts first
        static::assertSame('Dependency', $order[0]);
        static::assertSame('Dependent', $order[1]);
    }

    public function testMultipleDependentsIncreasePriority(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['Shared']);
        $graph->addNode('B', ['Shared']);
        $graph->addNode('C', ['Shared']);
        $graph->addNode('Shared', []);

        $order = $graph->getStartOrder();

        // Shared has 3 dependents, should be first
        static::assertSame('Shared', $order[0]);
    }

    public function testTransitiveDependencies(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('C', ['B']);
        $graph->addNode('B', ['A']);
        $graph->addNode('A', []);

        $order = $graph->getStartOrder();

        // A has most transitive dependents (B and C)
        static::assertSame('A', $order[0]);
        static::assertSame('B', $order[1]);
        static::assertSame('C', $order[2]);
    }

    public function testDetectsCycle(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['B']);
        $graph->addNode('B', ['A']);

        static::expectException(\LogicException::class);
        static::expectExceptionMessageMatches('/[Cc]ircular/');
        $graph->detectCycle();
    }

    public function testDetectsThreeNodeCycle(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['B']);
        $graph->addNode('B', ['C']);
        $graph->addNode('C', ['A']);

        static::expectException(\LogicException::class);
        $graph->detectCycle();
    }

    public function testNoCycleDoesNotThrow(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['B']);
        $graph->addNode('B', ['C']);
        $graph->addNode('C', []);

        $graph->detectCycle(); // Should not throw

        static::assertTrue(true);
    }

    public function testAddNodeWithUnknownDependencyAutoAdds(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['Unknown']);

        // Unknown should be auto-added with no deps
        $order = $graph->getStartOrder();

        static::assertCount(2, $order);
        static::assertContains('Unknown', $order);
    }
}
