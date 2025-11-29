<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Analysis;

use Closure;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects and parses PHPDoc blocks from PHP-Parser AST nodes.
 *
 * AST visitor that traverses PHP code to extract DocBlock comments and parse them
 * into structured phpDocumentor objects. Maintains namespace context for accurate
 * type resolution and handles use statements through ContextFactory integration.
 * Used during static analysis to build a complete map of documentation annotations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DocVisitor extends NodeVisitorAbstract
{
    /**
     * Current namespace context for resolving type names.
     *
     * Maintains the active namespace scope during AST traversal to enable
     * accurate resolution of relative class names and use statement imports
     * when parsing PHPDoc annotations.
     */
    private ?Context $context = null;

    /**
     * Collection of parsed DocBlock instances found during traversal.
     *
     * Accumulates all structured PHPDoc blocks encountered while visiting
     * AST nodes, providing a complete map of documentation annotations
     * for subsequent type extraction and dependency analysis.
     *
     * @var null|array<DocBlock>
     */
    private ?array $doc = null;

    /**
     * Create a new PHPDoc visitor instance.
     *
     * @param Closure(string): Context           $contextFactory Factory closure that creates Context instances
     *                                                           for namespace resolution and use statement handling.
     *                                                           Takes a namespace string and returns a configured
     *                                                           Context for type resolution within that namespace.
     * @param Closure(string, Context): DocBlock $phpdocFactory  Factory closure that parses PHPDoc strings
     *                                                           into DocBlock objects. Takes raw doc comment
     *                                                           text and a Context for type resolution, returning
     *                                                           a structured DocBlock representation.
     */
    public function __construct(
        private readonly Closure $contextFactory,
        private readonly Closure $phpdocFactory,
    ) {}

    /**
     * Create a visitor configured with default phpDocumentor factories.
     *
     * Factory method that initializes the visitor with standard phpDocumentor components
     * for parsing and resolving types. Sets up context factory for namespace handling and
     * DocBlock factory for comment parsing.
     *
     * @param  string $contents Full source code content used for context analysis and type resolution
     * @return self   Configured visitor ready for AST traversal
     */
    public static function create(string $contents): self
    {
        $contextInst = new ContextFactory();
        $context = fn (string $namespace): Context => $contextInst->createForNamespace($namespace, $contents);

        $phpdocInst = DocBlockFactory::createInstance();
        $phpdoc = fn (string $doc, Context $context): DocBlock => $phpdocInst->create($doc, $context);

        return new self($context, $phpdoc);
    }

    /**
     * Initialize visitor state before AST traversal begins.
     *
     * Resets internal state including namespace context and DocBlock collection to ensure
     * clean processing of each file. Called by PHP-Parser before visiting nodes.
     *
     * @param  array<Node>      $nodes Root nodes of the AST being traversed
     * @return null|array<Node> Optional modified node array, null indicates no changes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->resetContext();
        $this->doc = [];

        return null;
    }

    /**
     * Process each AST node to extract and parse PHPDoc comments.
     *
     * Visits every node during traversal to collect DocBlock comments. Updates namespace
     * context when entering namespace declarations and extracts doc comments from node
     * attributes for parsing into structured DocBlock objects.
     *
     * @param  Node $node Current AST node being visited
     * @return Node Unmodified node (visitor only collects data, doesn't transform AST)
     */
    public function enterNode(Node $node): Node
    {
        if ($node instanceof Namespace_) {
            $this->resetContext($node->name);
        }

        /** @var array<Comment> $comments */
        $comments = $node->getAttribute('comments', []);
        $this->recordDoc($comments);

        return $node;
    }

    /**
     * Get all collected DocBlock instances from the traversal.
     *
     * Returns the complete collection of parsed PHPDoc blocks found during AST traversal.
     * Should be called after traversal completes to retrieve results.
     *
     * @return null|array<DocBlock> Collection of parsed DocBlock instances, or null if not initialized
     */
    public function getDoc(): ?array
    {
        return $this->doc;
    }

    /**
     * Update the namespace context for type resolution.
     *
     * Creates a new Context instance for the specified namespace, enabling accurate
     * resolution of relative class names and use statement imports. Called when entering
     * namespace declarations or resetting state.
     *
     * @param null|Name $namespace Namespace node from the AST, or null for global namespace
     */
    private function resetContext(?Name $namespace = null): void
    {
        $callable = $this->contextFactory;
        $this->context = $callable($namespace?->toString() ?? '');
    }

    /**
     * Parse and record DocBlock comments from a collection of comment nodes.
     *
     * Filters for Doc comment instances (excluding regular comments) and parses them
     * into structured DocBlock objects using the configured factory. Requires context
     * to be initialized for proper type resolution during parsing.
     *
     * @param array<Comment> $comments Collection of comment nodes attached to an AST node
     */
    private function recordDoc(array $comments): void
    {
        $callable = $this->phpdocFactory;
        $context = $this->context;

        if (!$context instanceof Context) {
            return;
        }

        foreach ($comments as $comment) {
            if ($comment instanceof Doc) {
                $this->doc[] = $callable($comment->getText(), $context);
            }
        }
    }
}
