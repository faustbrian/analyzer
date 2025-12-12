<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeVisitorAbstract;

/**
 * Extracts fully qualified class name references from PHP code.
 *
 * AST visitor that identifies and collects all fully qualified class names used
 * throughout PHP code, excluding constants and functions. Transforms constant and
 * function call nodes to prevent false positives, then captures only FullyQualified
 * name nodes representing actual class references.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NameVisitor extends NodeVisitorAbstract
{
    /**
     * Collection of fully qualified class names found in the code.
     *
     * Accumulates class name references extracted from FullyQualified name nodes
     * during AST traversal, excluding constants and functions to provide accurate
     * class dependency tracking for static analysis and autoload optimization.
     *
     * @var null|array<string>
     */
    private ?array $names = null;

    /**
     * Initialize visitor state before AST traversal begins.
     *
     * Resets the names collection to ensure clean processing of each file.
     * Called by PHP-Parser before visiting nodes.
     *
     * @param  array<Node>      $nodes Root nodes of the AST being traversed
     * @return null|array<Node> Optional modified node array, null indicates no changes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->names = [];

        return null;
    }

    /**
     * Process each AST node to extract fully qualified class names.
     *
     * Visits every node during traversal to identify class references. Neutralizes
     * constant and function call nodes by replacing their names with whitespace to
     * prevent them from being collected as class references. Captures FullyQualified
     * nodes which represent explicit class name usage in the code.
     *
     * @param  Node $node Current AST node being visited
     * @return Node Potentially modified node (transforms function/const names to prevent false matches)
     */
    public function enterNode(Node $node): Node
    {
        // Neutralize function and constant names to exclude them from class reference collection
        if ($node instanceof ConstFetch || $node instanceof FuncCall) {
            $node->name = new Name(' ');
        }

        // Collect fully qualified class name references
        if ($node instanceof FullyQualified) {
            $this->names[] = $node->toString();
        }

        return $node;
    }

    /**
     * Get all collected fully qualified class names from the traversal.
     *
     * Returns the complete collection of class name references found during AST
     * traversal, excluding constants and functions. Should be called after traversal
     * completes to retrieve results.
     *
     * @return null|array<string> Collection of fully qualified class names, or null if not initialized
     */
    public function getNames(): ?array
    {
        return $this->names;
    }
}
