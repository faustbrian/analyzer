<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Data\AnalysisResult;
use Cline\Analyzer\Resolvers\AnalysisResolver;

test('it analyzes valid file successfully', function (): void {
    $resolver = new AnalysisResolver();
    $file = new SplFileInfo(__DIR__.'/../../Fixtures/ValidClass.php');
    $result = $resolver->analyze($file);

    expect($result)->toBeInstanceOf(AnalysisResult::class)
        ->and($result->success)->toBeTrue()
        ->and($result->missing)->toBeEmpty();
});

test('it detects missing class references', function (): void {
    $resolver = new AnalysisResolver();
    $file = new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php');
    $result = $resolver->analyze($file);

    expect($result)->toBeInstanceOf(AnalysisResult::class)
        ->and($result->success)->toBeFalse()
        ->and($result->missing)->not->toBeEmpty()
        ->and($result->hasMissing())->toBeTrue();
});

test('it respects ignore patterns', function (): void {
    $resolver = new AnalysisResolver(['NonExistent\\*']);
    $file = new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php');
    $result = $resolver->analyze($file);

    expect($result->missing)->not->toContain('NonExistent\\FakeClass');
});

test('it checks if class exists', function (): void {
    $resolver = new AnalysisResolver();

    expect($resolver->classExists(SplFileInfo::class))->toBeTrue()
        ->and($resolver->classExists('NonExistent\\FakeClass'))->toBeFalse();
});
