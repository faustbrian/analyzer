<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Analysis;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

use function explode;
use function in_array;
use function str_contains;

/**
 * Extracts translation key references from PHP code.
 *
 * AST visitor that collects translation keys from trans(), __(), and Lang::get() calls.
 * Identifies both static string literals and dynamic expressions, tracking file locations
 * for each reference to enable detailed validation reporting.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TranslationCallVisitor extends NodeVisitorAbstract
{
    /**
     * Collection of translation keys with metadata.
     *
     * Accumulates translation key references extracted from trans(), __(), Lang::get(),
     * and compiled Blade @lang directives during AST traversal. Each entry contains the
     * translation key (if static), line number, dynamic expression flag, method type,
     * JSON vs. file-based style detection, namespace information, empty key detection,
     * and package name extraction for comprehensive validation and reporting.
     *
     * @var null|array<array{key: null|string, line: int, dynamic: bool, type: string, json_style: bool, namespaced: bool, empty: bool, package: null|string, reason: null|string}>
     */
    private ?array $translations = null;

    /**
     * Initialize visitor state before AST traversal begins.
     *
     * @param  array<Node>      $nodes Root nodes of the AST being traversed
     * @return null|array<Node> Optional modified node array, null indicates no changes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->translations = [];

        return null;
    }

    /**
     * Process each AST node to extract translation key references.
     *
     * Identifies translation-related function and method calls including trans(), __(),
     * trans_choice(), Lang::get(), and compiled Blade @lang directives. Extracts translation
     * keys from the first argument, classifies them as static strings or dynamic expressions,
     * and detects JSON-style vs. file-based keys and namespaced package references.
     *
     * @param  Node $node Current AST node being visited
     * @return Node Unmodified node (visitor only collects data, doesn't transform AST)
     */
    public function enterNode(Node $node): Node
    {
        // Handle trans(), __(), and trans_choice() function calls
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $functionName = $node->name->toString();

            if (in_array($functionName, ['trans', '__', 'trans_choice'], true) && $node->args !== [] && $node->args[0] instanceof Arg) {
                $this->extractTranslationKey($node->args[0]->value, $node->getLine(), $functionName);
            }
        }

        // Handle Lang::get() static calls
        if ($node instanceof StaticCall
            && $node->class instanceof Name
            && $node->class->toString() === 'Lang'
            && $node->name instanceof Identifier
            && $node->name->toString() === 'get'
            && $node->args !== []
            && $node->args[0] instanceof Arg
        ) {
            $this->extractTranslationKey($node->args[0]->value, $node->getLine(), 'Lang::get');
        }

        // Handle app('translator')->get() calls (compiled Blade @lang directive)
        if ($node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === 'get'
            && $node->var instanceof FuncCall
            && $node->var->name instanceof Name
            && $node->var->name->toString() === 'app'
            && $node->var->args !== []
            && $node->var->args[0] instanceof Arg
            && $node->var->args[0]->value instanceof String_
            && $node->var->args[0]->value->value === 'translator'
            && $node->args !== []
            && $node->args[0] instanceof Arg
        ) {
            $this->extractTranslationKey($node->args[0]->value, $node->getLine(), 'translator::get');
        }

        return $node;
    }

    /**
     * Get all collected translation references from the traversal.
     *
     * @return null|array<array{key: null|string, line: int, dynamic: bool, type: string, json_style: bool, namespaced: bool, empty: bool, package: null|string, reason: null|string}>
     */
    public function getTranslationCalls(): ?array
    {
        return $this->translations;
    }

    /**
     * Extract translation key from argument expression.
     *
     * Analyzes the first argument node to determine if it contains a static string
     * literal or a dynamic expression. For static keys, detects JSON-style format
     * (no dots), namespaced package syntax (::), empty strings, and extracts package
     * names for validation. Dynamic keys record the expression type and reason.
     *
     * @param Node   $expr The expression containing the translation key
     * @param int    $line Line number where the call appears
     * @param string $type Method name (trans, __, Lang::get)
     */
    private function extractTranslationKey(Node $expr, int $line, string $type): void
    {
        // Static string literal
        if ($expr instanceof String_) {
            $key = $expr->value;
            // JSON translations don't have dots (no file prefix)
            $jsonStyle = !str_contains($key, '.');
            // Namespaced translations use :: syntax (e.g., 'package::file.key')
            $namespaced = str_contains($key, '::');
            // Empty keys
            $empty = $key === '';
            // Extract package name if namespaced
            $package = null;

            if ($namespaced) {
                $package = explode('::', $key, 2)[0];
            }

            $this->translations[] = [
                'key' => $key,
                'line' => $line,
                'dynamic' => false,
                'type' => $type,
                'json_style' => $jsonStyle,
                'namespaced' => $namespaced,
                'empty' => $empty,
                'package' => $package,
                'reason' => null,
            ];

            return;
        }

        // Dynamic expression (variable, concatenation, config, etc.)
        $reason = $this->getDynamicReason($expr);
        $this->translations[] = [
            'key' => null,
            'line' => $line,
            'dynamic' => true,
            'type' => $type,
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => $reason,
        ];
    }

    /**
     * Get reason why key is dynamic.
     *
     * Classifies the type of dynamic expression used as a translation key by analyzing
     * the AST node structure. Returns a human-readable description for reporting purposes,
     * helping developers understand why a translation reference cannot be statically
     * validated against language files.
     *
     * @param  Node   $expr The expression node to classify
     * @return string Human-readable reason describing the dynamic expression type
     */
    private function getDynamicReason(Node $expr): string
    {
        if ($expr instanceof Variable) {
            return 'Variable used as key';
        }

        if ($expr instanceof Concat) {
            return 'String concatenation';
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name) {
            $funcName = $expr->name->toString();

            return 'Function call used as key';
        }

        if ($expr instanceof StaticCall) {
            return 'Method call used as key';
        }

        if ($expr instanceof MethodCall) {
            return 'Method call used as key';
        }

        if ($expr instanceof Ternary) {
            return 'Ternary operator';
        }

        if ($expr instanceof Coalesce) {
            return 'Null coalescing operator';
        }

        return 'dynamic';
    }
}
