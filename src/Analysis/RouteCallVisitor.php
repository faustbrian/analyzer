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

use function is_string;
use function sprintf;
use function str_ends_with;

/**
 * Extracts route name references from PHP code.
 *
 * AST visitor that collects route names from route(), Route::has(), redirect()->route(),
 * and other route-related method calls. Identifies both static string literals and dynamic
 * expressions, tracking file locations for each reference.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RouteCallVisitor extends NodeVisitorAbstract
{
    /**
     * Collection of route names with metadata.
     *
     * Accumulates route name references extracted from route helper calls, Route facade
     * methods, and redirect chains during AST traversal. Each entry contains the route name
     * (if static), line number, whether the expression is dynamic, the method type used,
     * reason for dynamic classification, and empty string detection for validation purposes.
     *
     * @var null|array<array{name: null|string, line: int, dynamic: bool, type: string, reason: null|string, empty: bool}>
     */
    private ?array $routes = null;

    /**
     * Initialize visitor state before AST traversal begins.
     *
     * @param  array<Node>      $nodes Root nodes of the AST being traversed
     * @return null|array<Node> Optional modified node array, null indicates no changes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->routes = [];

        return null;
    }

    /**
     * Process each AST node to extract route name references.
     *
     * Identifies route-related function and method calls including route(), to_route(),
     * Route::has(), URL::route(), and chained ->route() methods. Extracts route names
     * from the first argument and classifies them as static strings or dynamic expressions.
     *
     * @param  Node $node Current AST node being visited
     * @return Node Unmodified node (visitor only collects data, doesn't transform AST)
     */
    public function enterNode(Node $node): Node
    {
        // Handle route() function calls
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $functionName = $node->name->toString();

            if ($functionName === 'route' && $node->args !== [] && $node->args[0] instanceof Arg) {
                $this->extractRouteName($node->args[0]->value, $node->getLine(), 'route');
            }

            if ($functionName === 'to_route' && $node->args !== [] && $node->args[0] instanceof Arg) {
                $this->extractRouteName($node->args[0]->value, $node->getLine(), 'to_route');
            }
        }

        // Handle Route::has() static calls
        if ($node instanceof StaticCall
            && $node->class instanceof Name
            && $node->name instanceof Identifier
            && $node->name->toString() === 'has'
            && $node->args !== []
            && $node->args[0] instanceof Arg
        ) {
            $className = $node->class->toString();

            if ($className === 'Route' || str_ends_with($className, '\Route')) {
                $this->extractRouteName($node->args[0]->value, $node->getLine(), 'Route::has');
            }
        }

        // Handle URL::route() static calls
        if ($node instanceof StaticCall
            && $node->class instanceof Name
            && $node->name instanceof Identifier
            && $node->name->toString() === 'route'
            && $node->args !== []
            && $node->args[0] instanceof Arg
        ) {
            $className = $node->class->toString();

            if ($className === 'URL' || str_ends_with($className, '\URL')) {
                $this->extractRouteName($node->args[0]->value, $node->getLine(), 'URL::route');
            }
        }

        // Handle redirect()->route(), response()->route(), url()->route(), $var->route()
        if ($node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === 'route'
            && $node->args !== []
            && $node->args[0] instanceof Arg
        ) {
            // Try to determine the method being called
            $type = 'method()->route';

            if ($node->var instanceof FuncCall && $node->var->name instanceof Name) {
                $funcName = $node->var->name->toString();
                $type = $funcName.'()->route';
            } elseif ($node->var instanceof Variable) {
                $varName = is_string($node->var->name) ? $node->var->name : 'var';
                $type = sprintf('$%s->route', $varName);
            }

            $this->extractRouteName($node->args[0]->value, $node->getLine(), $type);
        }

        return $node;
    }

    /**
     * Get all collected route references from the traversal.
     *
     * @return null|array<array{name: null|string, line: int, dynamic: bool, type: string, reason: null|string, empty: bool}>
     */
    public function getRouteCalls(): ?array
    {
        return $this->routes;
    }

    /**
     * Extract route name from argument expression.
     *
     * Analyzes the first argument node to determine if it contains a static string
     * literal or a dynamic expression. Static routes are stored with their literal
     * name value, while dynamic routes record the expression type and reason for
     * dynamic classification to aid in validation and reporting.
     *
     * @param Node   $expr The expression containing the route name
     * @param int    $line Line number where the call appears
     * @param string $type Method name (route(), Route::has(), etc.)
     */
    private function extractRouteName(Node $expr, int $line, string $type): void
    {
        // Static string literal
        if ($expr instanceof String_) {
            $name = $expr->value;
            $this->routes[] = [
                'name' => $name,
                'line' => $line,
                'dynamic' => false,
                'type' => $type,
                'reason' => null,
                'empty' => $name === '',
            ];

            return;
        }

        // Dynamic expression (variable, concatenation, config, etc.)
        $reason = $this->getDynamicReason($expr);
        $this->routes[] = [
            'name' => null,
            'line' => $line,
            'dynamic' => true,
            'type' => $type,
            'reason' => $reason,
            'empty' => false,
        ];
    }

    /**
     * Get reason why route name is dynamic.
     *
     * Classifies the type of dynamic expression used as a route name by analyzing
     * the AST node structure. Returns a human-readable description for reporting
     * purposes, helping developers understand why a route reference cannot be
     * statically validated.
     *
     * @param  Node   $expr The expression node to classify
     * @return string Human-readable reason describing the dynamic expression type
     */
    private function getDynamicReason(Node $expr): string
    {
        if ($expr instanceof Variable) {
            return 'Variable used as route name';
        }

        if ($expr instanceof Concat) {
            return 'String concatenation';
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name) {
            $funcName = $expr->name->toString();

            return 'Function call used as route name';
        }

        if ($expr instanceof StaticCall) {
            return 'Method call used as route name';
        }

        if ($expr instanceof MethodCall) {
            return 'Method call used as route name';
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
