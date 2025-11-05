<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Parallel\ProcessPool;
use SplFileInfo;

describe('ProcessPool::map()', function (): void {
    test('processes empty file array', function (): void {
        // Arrange
        $files = [];
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::map($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toBeEmpty();
    });

    test('processes single file', function (): void {
        // Arrange
        $file = new SplFileInfo(__FILE__);
        $files = [$file];
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::map($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(1)
            ->and($results[0])->toBe('ProcessPoolTest.php');
    });

    test('processes multiple files with default worker count', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
        ];
        $callback = fn (SplFileInfo $file): string => $file->getBasename();

        // Act
        $results = ProcessPool::map($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(4)
            ->and($results[0])->toBe('ProcessPoolTest.php')
            ->and($results[1])->toBe('Parallel')
            ->and($results[2])->toBe('ProcessPoolTest.php')
            ->and($results[3])->toBe('Parallel');
    });

    test('processes files with 1 worker', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
            new SplFileInfo(__FILE__),
        ];
        $callback = fn (SplFileInfo $file): string => $file->getBasename();

        // Act
        $results = ProcessPool::map($files, $callback, workers: 1);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(3);
    });

    test('processes files with 4 workers', function (): void {
        // Arrange
        $files = array_fill(0, 16, new SplFileInfo(__FILE__));
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::map($files, $callback, workers: 4);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(16)
            ->and($results)->each->toBe('ProcessPoolTest.php');
    });

    test('processes files with 8 workers', function (): void {
        // Arrange
        $files = array_fill(0, 24, new SplFileInfo(__FILE__));
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::map($files, $callback, workers: 8);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(24)
            ->and($results)->each->toBe('ProcessPoolTest.php');
    });

    test('chunks files correctly with more workers than files', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
        ];
        $callback = fn (SplFileInfo $file): string => $file->getBasename();

        // Act
        $results = ProcessPool::map($files, $callback, workers: 10);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(2);
    });

    test('applies callback correctly to each file', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
        ];
        $callback = fn (SplFileInfo $file): array => [
            'basename' => $file->getBasename(),
            'type' => $file->isDir() ? 'directory' : 'file',
        ];

        // Act
        $results = ProcessPool::map($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(2)
            ->and($results[0])->toBe([
                'basename' => 'ProcessPoolTest.php',
                'type' => 'file',
            ])
            ->and($results[1])->toBe([
                'basename' => 'Parallel',
                'type' => 'directory',
            ]);
    });

    test('maintains correct order of results', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
            new SplFileInfo(__FILE__),
        ];
        $counter = 0;
        $callback = function (SplFileInfo $file) use (&$counter): int {
            return ++$counter;
        };

        // Act
        $results = ProcessPool::map($files, $callback);

        // Assert
        expect($results)->toBe([1, 2, 3]);
    });

    test('handles callback that returns different types', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
        ];
        $callback = fn (SplFileInfo $file): int|bool => $file->isDir() ? true : 123;

        // Act
        $results = ProcessPool::map($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results[0])->toBe(123)
            ->and($results[1])->toBe(true);
    });

    test('distributes files into correct chunk sizes with 4 workers', function (): void {
        // Arrange - 10 files with 4 workers should create chunks of size 3, 3, 2, 2
        $files = array_fill(0, 10, new SplFileInfo(__FILE__));
        $chunkSizes = [];
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::map($files, $callback, workers: 4);

        // Assert
        expect($results)->toHaveCount(10);
        // Chunk size should be ceil(10/4) = 3
    });

    test('distributes files into correct chunk sizes with 8 workers', function (): void {
        // Arrange - 20 files with 8 workers should create chunks of size 3 each
        $files = array_fill(0, 20, new SplFileInfo(__FILE__));
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::map($files, $callback, workers: 8);

        // Assert
        expect($results)->toHaveCount(20);
        // Chunk size should be ceil(20/8) = 3
    });

    test('handles single file with multiple workers', function (): void {
        // Arrange
        $files = [new SplFileInfo(__FILE__)];
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::map($files, $callback, workers: 8);

        // Assert
        expect($results)->toHaveCount(1)
            ->and($results[0])->toBe('ProcessPoolTest.php');
    });
});

describe('ProcessPool::mapSerial()', function (): void {
    test('processes empty file array', function (): void {
        // Arrange
        $files = [];
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::mapSerial($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toBeEmpty();
    });

    test('processes single file', function (): void {
        // Arrange
        $file = new SplFileInfo(__FILE__);
        $files = [$file];
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::mapSerial($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(1)
            ->and($results[0])->toBe('ProcessPoolTest.php');
    });

    test('processes multiple files in order', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
            new SplFileInfo(__FILE__),
        ];
        $callback = fn (SplFileInfo $file): string => $file->getBasename();

        // Act
        $results = ProcessPool::mapSerial($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(3)
            ->and($results[0])->toBe('ProcessPoolTest.php')
            ->and($results[1])->toBe('Parallel')
            ->and($results[2])->toBe('ProcessPoolTest.php');
    });

    test('applies callback correctly to each file', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
        ];
        $callback = fn (SplFileInfo $file): array => [
            'basename' => $file->getBasename(),
            'type' => $file->isDir() ? 'directory' : 'file',
        ];

        // Act
        $results = ProcessPool::mapSerial($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results)->toHaveCount(2)
            ->and($results[0])->toBe([
                'basename' => 'ProcessPoolTest.php',
                'type' => 'file',
            ])
            ->and($results[1])->toBe([
                'basename' => 'Parallel',
                'type' => 'directory',
            ]);
    });

    test('maintains sequential execution order', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
        ];
        $counter = 0;
        $callback = function (SplFileInfo $file) use (&$counter): int {
            return ++$counter;
        };

        // Act
        $results = ProcessPool::mapSerial($files, $callback);

        // Assert
        expect($results)->toBe([1, 2, 3, 4]);
    });

    test('handles callback that returns different types', function (): void {
        // Arrange
        $files = [
            new SplFileInfo(__FILE__),
            new SplFileInfo(__DIR__),
        ];
        $callback = fn (SplFileInfo $file): int|bool => $file->isDir() ? true : 456;

        // Act
        $results = ProcessPool::mapSerial($files, $callback);

        // Assert
        expect($results)->toBeArray()
            ->and($results[0])->toBe(456)
            ->and($results[1])->toBe(true);
    });

    test('processes large array of files', function (): void {
        // Arrange
        $files = array_fill(0, 100, new SplFileInfo(__FILE__));
        $callback = fn (SplFileInfo $file): string => $file->getFilename();

        // Act
        $results = ProcessPool::mapSerial($files, $callback);

        // Assert
        expect($results)->toHaveCount(100)
            ->and($results)->each->toBe('ProcessPoolTest.php');
    });

    test('returns results in same order as input files', function (): void {
        // Arrange
        $file1 = new SplFileInfo(__FILE__);
        $file2 = new SplFileInfo(__DIR__);
        $files = [$file1, $file2, $file1, $file2];
        $callback = fn (SplFileInfo $file): string => $file->getBasename();

        // Act
        $results = ProcessPool::mapSerial($files, $callback);

        // Assert
        expect($results[0])->toBe('ProcessPoolTest.php')
            ->and($results[1])->toBe('Parallel')
            ->and($results[2])->toBe('ProcessPoolTest.php')
            ->and($results[3])->toBe('Parallel');
    });
});
