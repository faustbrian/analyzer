<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analysis\DocVisitor;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\Types\Context;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;

test('it can create visitor with default factory', function (): void {
    $contents = '<?php namespace App; class Test {}';

    $visitor = DocVisitor::create($contents);

    expect($visitor)->toBeInstanceOf(DocVisitor::class);
});

test('it initializes state before traversal', function (): void {
    $visitor = DocVisitor::create('<?php');

    $result = $visitor->beforeTraverse([]);

    expect($result)->toBeNull()
        ->and($visitor->getDoc())->toBe([]);
});

test('it collects DocBlock from node with doc comment', function (): void {
    $contents = '<?php namespace App;';
    $visitor = DocVisitor::create($contents);

    // Initialize visitor state
    $visitor->beforeTraverse([]);

    // Create namespace node
    $namespace = new Namespace_(
        new Name('App'),
    );
    $visitor->enterNode($namespace);

    // Create node with doc comment
    $node = new Class_('Test');
    $node->setAttribute('comments', [
        new Doc('/** @var string */'),
    ]);

    $visitor->enterNode($node);

    $docs = $visitor->getDoc();
    expect($docs)->toBeArray()
        ->and($docs)->toHaveCount(1)
        ->and($docs[0])->toBeInstanceOf(DocBlock::class);
});

test('it ignores nodes without doc comments', function (): void {
    $visitor = DocVisitor::create('<?php');

    $visitor->beforeTraverse([]);

    $node = new Class_('Test');
    $visitor->enterNode($node);

    expect($visitor->getDoc())->toBe([]);
});

test('it updates context when entering namespace', function (): void {
    $contents = '<?php namespace App\\Services;';
    $visitor = DocVisitor::create($contents);

    $visitor->beforeTraverse([]);

    // Enter namespace
    $namespace = new Namespace_(
        new Name('App\\Services'),
    );
    $visitor->enterNode($namespace);

    // Add doc comment in this namespace
    $node = new Class_('Test');
    $node->setAttribute('comments', [
        new Doc('/** @param string $test */'),
    ]);

    $visitor->enterNode($node);

    $docs = $visitor->getDoc();
    expect($docs)->toHaveCount(1);
});

test('it handles global namespace', function (): void {
    $visitor = DocVisitor::create('<?php');

    $visitor->beforeTraverse([]);

    // Enter global namespace (null name)
    $namespace = new Namespace_();
    $visitor->enterNode($namespace);

    $node = new Class_('Test');
    $node->setAttribute('comments', [
        new Doc('/** @return void */'),
    ]);

    $visitor->enterNode($node);

    expect($visitor->getDoc())->toHaveCount(1);
});

test('it processes multiple doc comments', function (): void {
    $contents = '<?php namespace App;';
    $visitor = DocVisitor::create($contents);

    $visitor->beforeTraverse([]);

    $namespace = new Namespace_(
        new Name('App'),
    );
    $visitor->enterNode($namespace);

    // First node with doc comment
    $node1 = new Class_('Test1');
    $node1->setAttribute('comments', [
        new Doc('/** @var string */'),
    ]);
    $visitor->enterNode($node1);

    // Second node with doc comment
    $node2 = new Class_('Test2');
    $node2->setAttribute('comments', [
        new Doc('/** @var int */'),
    ]);
    $visitor->enterNode($node2);

    expect($visitor->getDoc())->toHaveCount(2);
});

test('it handles multiple namespaces', function (): void {
    $contents = '<?php namespace App; namespace Other;';
    $visitor = DocVisitor::create($contents);

    $visitor->beforeTraverse([]);

    // First namespace
    $namespace1 = new Namespace_(
        new Name('App'),
    );
    $visitor->enterNode($namespace1);

    $node1 = new Class_('Test1');
    $node1->setAttribute('comments', [
        new Doc('/** @var string */'),
    ]);
    $visitor->enterNode($node1);

    // Second namespace
    $namespace2 = new Namespace_(
        new Name('Other'),
    );
    $visitor->enterNode($namespace2);

    $node2 = new Class_('Test2');
    $node2->setAttribute('comments', [
        new Doc('/** @var int */'),
    ]);
    $visitor->enterNode($node2);

    expect($visitor->getDoc())->toHaveCount(2);
});

test('it skips recording when context is not initialized', function (): void {
    // This tests line 169 - the edge case where recordDoc is called
    // when context is null (before resetContext initializes it)

    $contextFactory = fn (string $namespace): Context => new Context($namespace);
    $phpdocFactory = function (string $doc, Context $context): DocBlock {
        throw new RuntimeException('Should not be called when context is null');
    };

    $visitor = new DocVisitor($contextFactory, $phpdocFactory);

    // Do NOT call beforeTraverse, which would initialize doc array and context
    // Instead directly call enterNode to trigger recordDoc with null context

    $node = new Class_('Test');
    $node->setAttribute('comments', [
        new Doc('/** @var string */'),
    ]);

    // This should trigger the early return at line 169
    // because context is null (not initialized via resetContext)
    $result = $visitor->enterNode($node);

    expect($result)->toBe($node)
        ->and($visitor->getDoc())->toBeNull(); // Still null, never initialized
});

test('it filters out non-doc comments', function (): void {
    $contents = '<?php namespace App;';
    $visitor = DocVisitor::create($contents);

    $visitor->beforeTraverse([]);

    $namespace = new Namespace_(
        new Name('App'),
    );
    $visitor->enterNode($namespace);

    $node = new Class_('Test');
    $node->setAttribute('comments', [
        new Comment('// Regular comment'),
        new Comment('/* Block comment */'),
        new Doc('/** @var string */'), // Only this should be processed
    ]);

    $visitor->enterNode($node);

    // Only the Doc comment should be processed
    expect($visitor->getDoc())->toHaveCount(1);
});

test('it handles empty comments array', function (): void {
    $visitor = DocVisitor::create('<?php namespace App;');

    $visitor->beforeTraverse([]);

    $namespace = new Namespace_(
        new Name('App'),
    );
    $visitor->enterNode($namespace);

    $node = new Class_('Test');
    $node->setAttribute('comments', []);

    $visitor->enterNode($node);

    expect($visitor->getDoc())->toBe([]);
});

test('it resets state on each traversal', function (): void {
    $contents = '<?php namespace App;';
    $visitor = DocVisitor::create($contents);

    // First traversal
    $visitor->beforeTraverse([]);

    $namespace = new Namespace_(
        new Name('App'),
    );
    $visitor->enterNode($namespace);

    $node1 = new Class_('Test1');
    $node1->setAttribute('comments', [
        new Doc('/** @var string */'),
    ]);
    $visitor->enterNode($node1);

    expect($visitor->getDoc())->toHaveCount(1);

    // Second traversal - should reset
    $visitor->beforeTraverse([]);

    expect($visitor->getDoc())->toBe([]);

    // Add new doc
    $visitor->enterNode($namespace);
    $node2 = new Class_('Test2');
    $node2->setAttribute('comments', [
        new Doc('/** @var int */'),
    ]);
    $visitor->enterNode($node2);

    expect($visitor->getDoc())->toHaveCount(1);
});

test('it returns unmodified node from enterNode', function (): void {
    $visitor = DocVisitor::create('<?php');

    $visitor->beforeTraverse([]);

    $node = new Class_('Test');
    $result = $visitor->enterNode($node);

    expect($result)->toBe($node);
});
