<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Analysis;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitorAbstract;

/**
 * Extracts use statement imports from PHP code.
 *
 * AST visitor that collects only class imports declared with use statements,
 * filtering out function and constant imports. Traverses the PHP-Parser AST to
 * identify UseUse nodes within TYPE_NORMAL use statements and records their
 * fully qualified names for dependency tracking and analysis.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ImportVisitor extends NodeVisitorAbstract
{
    /**
     * Collection of imported class names from use statements.
     *
     * Accumulates fully qualified class names declared in TYPE_NORMAL use
     * statements during AST traversal, excluding function and constant imports
     * for precise dependency tracking.
     *
     * @var null|array<string>
     */
    private ?array $imports = null;

    /**
     * Current use statement type being processed.
     *
     * Tracks whether the visitor is currently within a class use (TYPE_NORMAL),
     * function use (TYPE_FUNCTION), or constant use (TYPE_CONSTANT) statement
     * to filter imports appropriately.
     */
    private int $currentUseType = Use_::TYPE_UNKNOWN;

    /**
     * Initialize visitor state before AST traversal begins.
     *
     * Resets the imports collection to ensure clean processing of each file.
     * Called by PHP-Parser before visiting nodes.
     *
     * @param  array<Node>      $nodes Root nodes of the AST being traversed
     * @return null|array<Node> Optional modified node array, null indicates no changes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->imports = [];

        return null;
    }

    /**
     * Process each AST node to extract use statement imports.
     *
     * Visits every node during traversal to identify Use_ statements and their UseUse
     * children. Tracks the current use statement type and only collects TYPE_NORMAL
     * (class) imports, filtering out function and constant imports.
     *
     * @param  Node $node Current AST node being visited
     * @return Node Unmodified node (visitor only collects data, doesn't transform AST)
     */
    public function enterNode(Node $node): Node
    {
        if ($node instanceof Use_) {
            $this->currentUseType = $node->type;
        } elseif ($node instanceof UseUse && $this->currentUseType === Use_::TYPE_NORMAL) {
            $this->imports[] = $node->name->toString();
        }

        return $node;
    }

    /**
     * Reset use type tracking when leaving a Use_ node.
     *
     * @param  Node $node Current AST node being left
     * @return Node Unmodified node
     */
    public function leaveNode(Node $node): Node
    {
        if ($node instanceof Use_) {
            $this->currentUseType = Use_::TYPE_UNKNOWN;
        }

        return $node;
    }

    /**
     * Get all collected import statements from the traversal.
     *
     * Returns the complete collection of fully qualified class names from use
     * statements found during AST traversal. Should be called after traversal
     * completes to retrieve results.
     *
     * @return null|array<string> Collection of imported class names, or null if not initialized
     */
    public function getImports(): ?array
    {
        return $this->imports;
    }
}
