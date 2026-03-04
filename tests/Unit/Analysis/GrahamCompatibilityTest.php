<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analysis\ClassInspector;
use Cline\Analyzer\Analysis\ReferenceAnalyzer;
use Cline\Analyzer\Exceptions\EmptyClassNameException;
use Tests\TestCase;

// Tests ported from graham-campbell/analyzer to verify behavior compatibility

describe('ClassInspector (graham compatibility)', function (): void {
    test('it can inspect classes with references', function (): void {
        $inspector = ClassInspector::inspect(TestCase::class);

        expect($inspector)->toBeInstanceOf(ClassInspector::class)
            ->and($inspector->isClass())->toBeTrue()
            ->and($inspector->isInterface())->toBeFalse()
            ->and($inspector->isTrait())->toBeFalse()
            ->and($inspector->exists())->toBeTrue()
            ->and($inspector->references())->toBeArray();
    });

    test('it can inspect interfaces', function (): void {
        $inspector = ClassInspector::inspect(Throwable::class);

        expect($inspector)->toBeInstanceOf(ClassInspector::class)
            ->and($inspector->isClass())->toBeFalse()
            ->and($inspector->isInterface())->toBeTrue()
            ->and($inspector->isTrait())->toBeFalse()
            ->and($inspector->exists())->toBeTrue();
    });

    test('it can inspect nothing', function (): void {
        $inspector = ClassInspector::inspect('foobarbaz');

        expect($inspector)->toBeInstanceOf(ClassInspector::class)
            ->and($inspector->isClass())->toBeFalse()
            ->and($inspector->isInterface())->toBeFalse()
            ->and($inspector->isTrait())->toBeFalse()
            ->and($inspector->exists())->toBeFalse()
            ->and($inspector->references())->toBe([]);
    });

    test('it cannot inspect empty string', function (): void {
        ClassInspector::inspect('');
    })->throws(EmptyClassNameException::class, 'The class name must be non-empty.');
});

describe('ReferenceAnalyzer (graham compatibility)', function (): void {
    test('it can generate refs from current file', function (): void {
        $refs = new ReferenceAnalyzer()->analyze(__FILE__);

        expect($refs)->toBeArray()
            ->and($refs)->toContain(ClassInspector::class)
            ->and($refs)->toContain(ReferenceAnalyzer::class);
    });

    test('it can generate refs from func stub', function (): void {
        $refs = new ReferenceAnalyzer()->analyze(__DIR__.'/../../stubs/func.php');

        expect($refs)->toBe([]);
    });

    test('it can generate refs from bool stub', function (): void {
        $refs = new ReferenceAnalyzer()->analyze(__DIR__.'/../../stubs/bool.php');

        expect($refs)->toBe([]);
    });

    test('it can generate refs from eg stub', function (): void {
        $refs = new ReferenceAnalyzer()->analyze(__DIR__.'/../../stubs/eg.php');

        // Order may vary, just check both are present
        expect($refs)->toBeArray()
            ->and($refs)->toHaveCount(1)
            ->and($refs)->toContain('Foo\\Bar');
    });
});
