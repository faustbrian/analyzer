<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Resolvers\FileResolver;

/**
 * Test: FileResolver retrieves all PHP files from a directory.
 *
 * Validates that FileResolver recursively discovers all PHP files within
 * a given directory path, returning a non-empty array of SplFileInfo objects
 * where each file has a .php extension.
 */
test('it gets all php files from directory', function (): void {
    $resolver = new FileResolver();
    $files = $resolver->getFiles([__DIR__.'/../../Fixtures']);

    expect($files)->toBeArray()
        ->and(count($files))->toBeGreaterThan(0);

    foreach ($files as $file) {
        expect($file)->toBeInstanceOf(SplFileInfo::class)
            ->and($file->getExtension())->toBe('php');
    }
});

/**
 * Test: FileResolver excludes non-PHP files from results.
 *
 * Ensures that FileResolver only returns files with .php extension,
 * filtering out all other file types during directory traversal.
 */
test('it filters out non-php files', function (): void {
    $resolver = new FileResolver();
    $files = $resolver->getFiles([__DIR__.'/../../']);

    foreach ($files as $file) {
        expect($file->getExtension())->toBe('php');
    }
});

/**
 * Test: FileResolver handles single file path input.
 *
 * Verifies that FileResolver correctly processes individual file paths
 * (not directories), returning an array containing exactly the specified file.
 */
test('it handles single file path', function (): void {
    $resolver = new FileResolver();
    $files = $resolver->getFiles([__DIR__.'/../../Fixtures/ValidClass.php']);

    expect($files)->toHaveCount(1)
        ->and($files[0]->getFilename())->toBe('ValidClass.php');
});

/**
 * Test: FileResolver respects exclude patterns for filtering files.
 *
 * Validates that FileResolver correctly applies exclude patterns during
 * file discovery, preventing files in matching paths from being included
 * in the result set.
 */
test('it excludes files matching patterns', function (): void {
    $resolver = new FileResolver(['Fixtures']);
    $files = $resolver->getFiles([__DIR__.'/../../']);

    foreach ($files as $file) {
        expect($file->getPathname())->not->toContain('Fixtures');
    }
});

test('it excludes directories with glob patterns', function (): void {
    $resolver = new FileResolver(['Fixtures/*']);
    $files = $resolver->getFiles([__DIR__.'/../../']);

    foreach ($files as $file) {
        expect($file->getPathname())->not->toContain('Fixtures/');
    }
});

test('it supports multiple exclude patterns', function (): void {
    $resolver = new FileResolver(['Fixtures', 'stubs']);
    $files = $resolver->getFiles([__DIR__.'/../../']);

    foreach ($files as $file) {
        expect($file->getPathname())->not->toContain('Fixtures')
            ->and($file->getPathname())->not->toContain('stubs');
    }
});

test('it excludes hidden files', function (): void {
    $resolver = new FileResolver();
    $files = $resolver->getFiles([__DIR__.'/../../']);

    foreach ($files as $file) {
        expect($file->getFilename())->not->toStartWith('.');
    }
});

test('it works with empty exclude patterns', function (): void {
    $resolver = new FileResolver([]);
    $files = $resolver->getFiles([__DIR__.'/../../Fixtures']);

    expect($files)->toBeArray()
        ->and(count($files))->toBeGreaterThan(0);
});
