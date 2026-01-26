<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\DependencyGraph;

final class DependencyGraphTest extends TestCase
{
    public function testEmptyGraphReturnsEmptyOrder(): void
    {
        $graph = new DependencyGraph();

        $this->assertSame([], $graph->getStartOrder());
    }

    public function testSingleNodeWithNoDependencies(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', []);

        $this->assertSame(['A'], $graph->getStartOrder());
    }

    public function testNodeWithDependentsStartsFirst(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('Dependent', ['Dependency']);
        $graph->addNode('Dependency', []);

        $order = $graph->getStartOrder();

        // Dependency has a dependent, so starts first
        $this->assertSame('Dependency', $order[0]);
        $this->assertSame('Dependent', $order[1]);
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
        $this->assertSame('Shared', $order[0]);
    }

    public function testTransitiveDependencies(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('C', ['B']);
        $graph->addNode('B', ['A']);
        $graph->addNode('A', []);

        $order = $graph->getStartOrder();

        // A has most transitive dependents (B and C)
        $this->assertSame('A', $order[0]);
        $this->assertSame('B', $order[1]);
        $this->assertSame('C', $order[2]);
    }

    public function testDetectsCycle(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['B']);
        $graph->addNode('B', ['A']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/[Cc]ircular/');
        $graph->detectCycle();
    }

    public function testDetectsThreeNodeCycle(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['B']);
        $graph->addNode('B', ['C']);
        $graph->addNode('C', ['A']);

        $this->expectException(\LogicException::class);
        $graph->detectCycle();
    }

    public function testNoCycleDoesNotThrow(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['B']);
        $graph->addNode('B', ['C']);
        $graph->addNode('C', []);

        $graph->detectCycle(); // Should not throw

        $this->assertTrue(true);
    }

    public function testAddNodeWithUnknownDependencyAutoAdds(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode('A', ['Unknown']);

        // Unknown should be auto-added with no deps
        $order = $graph->getStartOrder();

        $this->assertCount(2, $order);
        $this->assertContains('Unknown', $order);
    }
}
