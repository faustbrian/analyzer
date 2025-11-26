<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analysis\TranslationCallVisitor;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
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

test('it initializes state before traversal', function (): void {
    $visitor = new TranslationCallVisitor();

    $result = $visitor->beforeTraverse([]);

    expect($result)->toBeNull()
        ->and($visitor->getTranslationCalls())->toBe([]);
});

test('it extracts static translation key from trans() function', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // Create trans('users.name') call
    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('users.name'),
        )],
    );
    $node->setAttribute('startLine', 10);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => 'users.name',
            'line' => 10,
            'dynamic' => false,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => null,
        ]);
});

test('it extracts static translation key from __() function', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // Create __('messages.welcome') call
    $node = new FuncCall(
        new Name('__'),
        [new Arg(
            new String_('messages.welcome'),
        )],
    );
    $node->setAttribute('startLine', 15);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => 'messages.welcome',
            'line' => 15,
            'dynamic' => false,
            'type' => '__',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => null,
        ]);
});

test('it extracts static translation key from trans_choice() function', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // Create trans_choice('items.count', 5) call
    $node = new FuncCall(
        new Name('trans_choice'),
        [new Arg(
            new String_('items.count'),
        )],
    );
    $node->setAttribute('startLine', 20);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => 'items.count',
            'line' => 20,
            'dynamic' => false,
            'type' => 'trans_choice',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => null,
        ]);
});

test('it extracts static translation key from Lang::get() static call', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // Create Lang::get('app.title') call
    $node = new StaticCall(
        new Name('Lang'),
        new Identifier('get'),
        [new Arg(
            new String_('app.title'),
        )],
    );
    $node->setAttribute('startLine', 25);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => 'app.title',
            'line' => 25,
            'dynamic' => false,
            'type' => 'Lang::get',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => null,
        ]);
});

test('it extracts static translation key from app(translator)->get() call', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // Create app('translator')->get('validation.required') call
    $appCall = new FuncCall(
        new Name('app'),
        [new Arg(
            new String_('translator'),
        )],
    );

    $node = new MethodCall(
        $appCall,
        new Identifier('get'),
        [new Arg(
            new String_('validation.required'),
        )],
    );
    $node->setAttribute('startLine', 30);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => 'validation.required',
            'line' => 30,
            'dynamic' => false,
            'type' => 'translator::get',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => null,
        ]);
});

test('it detects JSON style translation keys', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // JSON keys don't have dots
    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('Welcome to our application'),
        )],
    );
    $node->setAttribute('startLine', 35);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['json_style'])->toBeTrue()
        ->and($calls[0]['key'])->toBe('Welcome to our application');
});

test('it detects namespaced translation keys', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // Namespaced keys use :: syntax
    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('package::messages.welcome'),
        )],
    );
    $node->setAttribute('startLine', 40);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['namespaced'])->toBeTrue()
        ->and($calls[0]['package'])->toBe('package')
        ->and($calls[0]['key'])->toBe('package::messages.welcome');
});

test('it extracts package name from namespaced keys', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // Test various package names
    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('my-package::file.key'),
        )],
    );
    $node->setAttribute('startLine', 45);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['package'])->toBe('my-package');
});

test('it detects empty translation keys', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_(''),
        )],
    );
    $node->setAttribute('startLine', 50);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['empty'])->toBeTrue()
        ->and($calls[0]['key'])->toBe('');
});

test('it detects variable as dynamic translation key', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new Variable('key'),
        )],
    );
    $node->setAttribute('startLine', 55);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => null,
            'line' => 55,
            'dynamic' => true,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => 'Variable used as key',
        ]);
});

test('it detects string concatenation as dynamic translation key', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new Concat(
                new String_('messages.'),
                new Variable('type'),
            ),
        )],
    );
    $node->setAttribute('startLine', 60);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => null,
            'line' => 60,
            'dynamic' => true,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => 'String concatenation',
        ]);
});

test('it detects function call as dynamic translation key', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // trans(config('app.locale'))
    $configCall = new FuncCall(
        new Name('config'),
        [new Arg(
            new String_('app.locale'),
        )],
    );

    $node = new FuncCall(
        new Name('trans'),
        [new Arg($configCall)],
    );
    $node->setAttribute('startLine', 65);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => null,
            'line' => 65,
            'dynamic' => true,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => 'Function call used as key',
        ]);
});

test('it detects static method call as dynamic translation key', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // trans(Config::get('app.locale'))
    $staticCall = new StaticCall(
        new Name('Config'),
        new Identifier('get'),
        [new Arg(
            new String_('app.locale'),
        )],
    );

    $node = new FuncCall(
        new Name('trans'),
        [new Arg($staticCall)],
    );
    $node->setAttribute('startLine', 70);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => null,
            'line' => 70,
            'dynamic' => true,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => 'Method call used as key',
        ]);
});

test('it detects instance method call as dynamic translation key', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // trans($user->getLocaleKey())
    $methodCall = new MethodCall(
        new Variable('user'),
        new Identifier('getLocaleKey'),
    );

    $node = new FuncCall(
        new Name('trans'),
        [new Arg($methodCall)],
    );
    $node->setAttribute('startLine', 75);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => null,
            'line' => 75,
            'dynamic' => true,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => 'Method call used as key',
        ]);
});

test('it detects ternary operator as dynamic translation key', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // trans($isAdmin ? 'admin.welcome' : 'user.welcome')
    $ternary = new Ternary(
        new Variable('isAdmin'),
        new String_('admin.welcome'),
        new String_('user.welcome'),
    );

    $node = new FuncCall(
        new Name('trans'),
        [new Arg($ternary)],
    );
    $node->setAttribute('startLine', 80);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => null,
            'line' => 80,
            'dynamic' => true,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => 'Ternary operator',
        ]);
});

test('it detects null coalescing operator as dynamic translation key', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // trans($customKey ?? 'default.key')
    $coalesce = new Coalesce(
        new Variable('customKey'),
        new String_('default.key'),
    );

    $node = new FuncCall(
        new Name('trans'),
        [new Arg($coalesce)],
    );
    $node->setAttribute('startLine', 85);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => null,
            'line' => 85,
            'dynamic' => true,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => 'Null coalescing operator',
        ]);
});

test('it handles unknown dynamic expression types', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // Use an Array_ node as an unknown expression type (not covered by specific cases)
    $unknownExpr = new Array_();

    $node = new FuncCall(
        new Name('trans'),
        [new Arg($unknownExpr)],
    );
    $node->setAttribute('startLine', 90);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0])->toMatchArray([
            'key' => null,
            'line' => 90,
            'dynamic' => true,
            'type' => 'trans',
            'json_style' => false,
            'namespaced' => false,
            'empty' => false,
            'package' => null,
            'reason' => 'dynamic',
        ]);
});

test('it ignores function calls without arguments', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new FuncCall(
        new Name('trans'),
        [],
    );

    $visitor->enterNode($node);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it ignores Lang::get() calls without arguments', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new StaticCall(
        new Name('Lang'),
        new Identifier('get'),
        [],
    );

    $visitor->enterNode($node);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it ignores app(translator)->get() calls without arguments', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $appCall = new FuncCall(
        new Name('app'),
        [new Arg(
            new String_('translator'),
        )],
    );

    $node = new MethodCall(
        $appCall,
        new Identifier('get'),
        [],
    );

    $visitor->enterNode($node);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it ignores non-translation function calls', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new FuncCall(
        new Name('someOtherFunction'),
        [new Arg(
            new String_('test'),
        )],
    );

    $visitor->enterNode($node);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it ignores static calls to non-Lang classes', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new StaticCall(
        new Name('Config'),
        new Identifier('get'),
        [new Arg(
            new String_('app.name'),
        )],
    );

    $visitor->enterNode($node);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it ignores Lang static calls to non-get methods', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new StaticCall(
        new Name('Lang'),
        new Identifier('has'),
        [new Arg(
            new String_('test'),
        )],
    );

    $visitor->enterNode($node);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it ignores app() calls with non-translator argument', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $appCall = new FuncCall(
        new Name('app'),
        [new Arg(
            new String_('config'),
        )],
    );

    $node = new MethodCall(
        $appCall,
        new Identifier('get'),
        [new Arg(
            new String_('test'),
        )],
    );

    $visitor->enterNode($node);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it ignores method calls that are not on app() functions', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new MethodCall(
        new Variable('someObject'),
        new Identifier('get'),
        [new Arg(
            new String_('test'),
        )],
    );

    $visitor->enterNode($node);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it collects multiple translation calls', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // First call
    $node1 = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('users.name'),
        )],
    );
    $node1->setAttribute('startLine', 10);

    $visitor->enterNode($node1);

    // Second call
    $node2 = new FuncCall(
        new Name('__'),
        [new Arg(
            new Variable('key'),
        )],
    );
    $node2->setAttribute('startLine', 15);

    $visitor->enterNode($node2);

    // Third call
    $node3 = new StaticCall(
        new Name('Lang'),
        new Identifier('get'),
        [new Arg(
            new String_('package::file.key'),
        )],
    );
    $node3->setAttribute('startLine', 20);

    $visitor->enterNode($node3);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(3)
        ->and($calls[0]['key'])->toBe('users.name')
        ->and($calls[1]['key'])->toBeNull()
        ->and($calls[1]['reason'])->toBe('Variable used as key')
        ->and($calls[2]['key'])->toBe('package::file.key')
        ->and($calls[2]['package'])->toBe('package');
});

test('it resets state on each traversal', function (): void {
    $visitor = new TranslationCallVisitor();

    // First traversal
    $visitor->beforeTraverse([]);

    $node1 = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('users.name'),
        )],
    );
    $node1->setAttribute('startLine', 10);

    $visitor->enterNode($node1);

    expect($visitor->getTranslationCalls())->toHaveCount(1);

    // Second traversal - should reset
    $visitor->beforeTraverse([]);

    expect($visitor->getTranslationCalls())->toBe([]);
});

test('it returns unmodified node from enterNode', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('test'),
        )],
    );

    $result = $visitor->enterNode($node);

    expect($result)->toBe($node);
});

test('it handles complex namespaced package names', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('vendor/package::messages.welcome'),
        )],
    );
    $node->setAttribute('startLine', 100);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['package'])->toBe('vendor/package')
        ->and($calls[0]['namespaced'])->toBeTrue();
});

test('it differentiates JSON style from file-based keys', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // File-based key (has dot)
    $node1 = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('validation.required'),
        )],
    );
    $node1->setAttribute('startLine', 10);

    $visitor->enterNode($node1);

    // JSON key (no dot)
    $node2 = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('Welcome User'),
        )],
    );
    $node2->setAttribute('startLine', 20);

    $visitor->enterNode($node2);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(2)
        ->and($calls[0]['json_style'])->toBeFalse()
        ->and($calls[1]['json_style'])->toBeTrue();
});

test('it handles edge case of namespaced JSON style key', function (): void {
    $visitor = new TranslationCallVisitor();
    $visitor->beforeTraverse([]);

    // This is technically valid - namespaced but no dots after ::
    $node = new FuncCall(
        new Name('trans'),
        [new Arg(
            new String_('package::Welcome'),
        )],
    );
    $node->setAttribute('startLine', 50);

    $visitor->enterNode($node);

    $calls = $visitor->getTranslationCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['json_style'])->toBeTrue() // No dot in the key means JSON style
        ->and($calls[0]['namespaced'])->toBeTrue()
        ->and($calls[0]['package'])->toBe('package');
});
