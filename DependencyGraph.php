<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

/**
 * Directed Acyclic Graph for ViewModel dependency resolution.
 *
 * Provides topological ordering with priority given to nodes
 * that have the most dependents (they should start first).
 */
final class DependencyGraph
{
    /** @var array<string, list<string>> node -> dependencies */
    private array $edges = [];

    /** @var array<string, int> node -> count of direct dependents */
    private array $dependentCount = [];

    /**
     * Add a node with its dependencies.
     *
     * @param string $class The ViewModel class name
     * @param list<string> $dependencies Classes this node depends on
     */
    public function addNode(string $class, array $dependencies): void
    {
        $this->edges[$class] = $dependencies;

        // Initialize dependent count for this node if not set
        if (!isset($this->dependentCount[$class])) {
            $this->dependentCount[$class] = 0;
        }

        // Increment dependent count for each dependency
        foreach ($dependencies as $dep) {
            // Auto-add unknown dependencies with no deps
            if (!isset($this->edges[$dep])) {
                $this->edges[$dep] = [];
            }
            if (!isset($this->dependentCount[$dep])) {
                $this->dependentCount[$dep] = 0;
            }
            $this->dependentCount[$dep]++;
        }
    }

    /**
     * Get nodes in start order: most dependents first.
     *
     * Uses topological sort with priority by dependent count.
     *
     * @return list<string>
     */
    public function getStartOrder(): array
    {
        if ($this->edges === []) {
            return [];
        }

        // Calculate transitive dependent counts using reverse topological order
        $transitiveCounts = $this->calculateTransitiveDependentCounts();

        // Sort by transitive dependent count (descending)
        $nodes = array_keys($this->edges);
        usort($nodes, static fn($a, $b) => $transitiveCounts[$b] <=> $transitiveCounts[$a]);

        return $nodes;
    }

    /**
     * Detect cycles in the graph.
     *
     * @throws \LogicException if a cycle is detected
     */
    public function detectCycle(): void
    {
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($this->edges) as $node) {
            if ($this->hasCycle($node, $visited, $recursionStack)) {
                throw new \LogicException(sprintf('Circular ViewModel dependency detected: %s', implode(
                    ' -> ',
                    $recursionStack,
                )));
            }
        }
    }

    /**
     * @param array<string, bool> $visited
     * @param list<string> $recursionStack
     */
    private function hasCycle(string $node, array &$visited, array &$recursionStack): bool
    {
        if (in_array($node, $recursionStack, strict: true)) {
            $recursionStack[] = $node; // Add to show the cycle
            return true;
        }

        if (isset($visited[$node])) {
            return false;
        }

        $visited[$node] = true;
        $recursionStack[] = $node;

        foreach ($this->edges[$node] ?? [] as $dep) {
            if ($this->hasCycle($dep, $visited, $recursionStack)) {
                return true;
            }
        }

        array_pop($recursionStack);
        return false;
    }

    /**
     * Calculate transitive dependent count for each node.
     *
     * A node's transitive count = direct dependents + all their transitive dependents.
     *
     * @return array<string, int>
     */
    private function calculateTransitiveDependentCounts(): array
    {
        // Build reverse graph (dependents instead of dependencies)
        $reverseDeps = [];
        foreach ($this->edges as $node => $deps) {
            $reverseDeps[$node] ??= [];
            foreach ($deps as $dep) {
                $reverseDeps[$dep][] = $node;
            }
        }

        // For each node, count all transitive dependents via BFS
        $counts = [];
        foreach (array_keys($this->edges) as $node) {
            $counts[$node] = $this->countTransitiveDependents($node, $reverseDeps);
        }

        return $counts;
    }

    /**
     * @param array<string, list<string>> $reverseDeps
     */
    private function countTransitiveDependents(string $node, array $reverseDeps): int
    {
        $visited = [];
        $queue = $reverseDeps[$node] ?? [];
        $count = 0;

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $count++;

            foreach ($reverseDeps[$current] ?? [] as $dependent) {
                if (!isset($visited[$dependent])) {
                    $queue[] = $dependent;
                }
            }
        }

        return $count;
    }
}
