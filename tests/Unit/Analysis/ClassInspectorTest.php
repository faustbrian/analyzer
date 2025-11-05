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

test('it can inspect a class', function (): void {
    $inspector = ClassInspector::inspect(SplFileInfo::class);

    expect($inspector)->toBeInstanceOf(ClassInspector::class)
        ->and($inspector->isClass())->toBeTrue()
        ->and($inspector->exists())->toBeTrue();
});

test('it can inspect an interface', function (): void {
    $inspector = ClassInspector::inspect(Throwable::class);

    expect($inspector->isInterface())->toBeTrue()
        ->and($inspector->exists())->toBeTrue();
});

test('it can inspect a trait', function (): void {
    $inspector = ClassInspector::inspect('GrahamCampbell\\Analyzer\\AnalysisTrait');

    if (trait_exists('GrahamCampbell\\Analyzer\\AnalysisTrait')) {
        expect($inspector->isTrait())->toBeTrue()
            ->and($inspector->exists())->toBeTrue();
    } else {
        expect($inspector->exists())->toBeFalse();
    }
});

test('it returns false for non-existent class', function (): void {
    $inspector = ClassInspector::inspect('NonExistent\\FakeClass');

    expect($inspector->exists())->toBeFalse();
});

test('it throws exception for empty class name', function (): void {
    ClassInspector::inspect('');
})->throws(EmptyClassNameException::class, 'The class name must be non-empty.');

test('it throws exception for zero string class name', function (): void {
    ClassInspector::inspect('0');
})->throws(EmptyClassNameException::class, 'The class name must be non-empty.');

test('it returns reflection instance for existing class', function (): void {
    $inspector = ClassInspector::inspect(SplFileInfo::class);

    $refector = $inspector->refector();

    expect($refector)->toBeInstanceOf(ReflectionClass::class)
        ->and($refector->getName())->toBe(SplFileInfo::class);
});

test('it returns null reflection for non-existent class', function (): void {
    $inspector = ClassInspector::inspect('NonExistent\\FakeClass');

    expect($inspector->refector())->toBeNull();
});

test('it returns empty array for references when class does not exist', function (): void {
    $inspector = ClassInspector::inspect('NonExistent\\FakeClass');

    expect($inspector->references())->toBe([]);
});

test('it returns empty array for references when file name is not available', function (): void {
    // Internal PHP classes don't have a file name
    $inspector = ClassInspector::inspect(SplFileInfo::class);

    expect($inspector->references())->toBe([]);
});

test('it returns references for user-defined class', function (): void {
    // Use the ClassInspector class itself as it's a user-defined class in this codebase
    $inspector = ClassInspector::inspect(ClassInspector::class);

    $references = $inspector->references();

    expect($references)->toBeArray()
        ->and($references)->not->toBeEmpty()
        ->and($references)->toContain(EmptyClassNameException::class)
        ->and($references)->toContain(ReflectionClass::class)
        ->and($references)->toContain(ReferenceAnalyzer::class);
});
