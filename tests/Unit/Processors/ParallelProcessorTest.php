<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Exceptions\InvalidWorkerCountException;
use Cline\Analyzer\Processors\ParallelProcessor;

test('it creates processor with default workers', function (): void {
    // Arrange & Act
    $processor = new ParallelProcessor();

    // Assert
    expect($processor)->toBeInstanceOf(ParallelProcessor::class);
});

test('it creates processor with custom worker count', function (): void {
    // Arrange & Act
    $processor = new ParallelProcessor(workers: 8);

    // Assert
    expect($processor)->toBeInstanceOf(ParallelProcessor::class);
});

test('it throws exception when workers is zero', function (): void {
    // Arrange & Act & Assert
    expect(fn (): ParallelProcessor => new ParallelProcessor(workers: 0))
        ->toThrow(InvalidWorkerCountException::class, 'Workers must be at least 1, got 0.');
});

test('it throws exception when workers is negative', function (): void {
    // Arrange & Act & Assert
    expect(fn (): ParallelProcessor => new ParallelProcessor(workers: -1))
        ->toThrow(InvalidWorkerCountException::class, 'Workers must be at least 1, got -1.');
});

test('it processes files in chunks', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 2);
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__FILE__),
        new SplFileInfo(__FILE__),
        new SplFileInfo(__FILE__),
    ];
    $callback = fn (SplFileInfo $file): string => $file->getFilename();

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toHaveCount(4)
        ->and($results)->each->toBe(basename(__FILE__));
});

test('it handles single file', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 4);
    $files = [new SplFileInfo(__FILE__)];
    $callback = fn (SplFileInfo $file): string => $file->getFilename();

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toHaveCount(1)
        ->and($results[0])->toBe(basename(__FILE__));
});

test('it handles empty file array', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 4);
    $files = [];
    $callback = fn (SplFileInfo $file): string => $file->getFilename();

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toBeEmpty();
});

test('it distributes files across workers efficiently', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 3);
    $files = array_fill(0, 10, new SplFileInfo(__FILE__));
    $counter = 0;
    $callback = function (SplFileInfo $file) use (&$counter): int {
        return ++$counter;
    };

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toHaveCount(10)
        ->and($results)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
});

test('it handles more workers than files', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 10);
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__FILE__),
    ];
    $callback = fn (SplFileInfo $file): string => $file->getFilename();

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toHaveCount(2)
        ->and($results)->each->toBe(basename(__FILE__));
});

test('it processes files with complex callback', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 2);
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__DIR__),
    ];
    $callback = fn (SplFileInfo $file): array => [
        'name' => $file->getFilename(),
        'type' => $file->getType(),
    ];

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toHaveCount(2)
        ->and($results[0])->toHaveKeys(['name', 'type'])
        ->and($results[1])->toHaveKeys(['name', 'type']);
});

test('it maintains processing order', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 2);
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__DIR__),
        new SplFileInfo(__FILE__),
    ];
    $callback = fn (SplFileInfo $file): string => $file->getType();

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toBe(['file', 'dir', 'file']);
});

test('it handles single worker', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 1);
    $files = array_fill(0, 5, new SplFileInfo(__FILE__));
    $callback = fn (SplFileInfo $file): string => $file->getFilename();

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toHaveCount(5)
        ->and($results)->each->toBe(basename(__FILE__));
});

test('it processes with maximum workers', function (): void {
    // Arrange
    $processor = new ParallelProcessor(workers: 100);
    $files = array_fill(0, 3, new SplFileInfo(__FILE__));
    $callback = fn (SplFileInfo $file): string => $file->getFilename();

    // Act
    $results = $processor->process($files, $callback);

    // Assert
    expect($results)->toHaveCount(3)
        ->and($results)->each->toBe(basename(__FILE__));
});
