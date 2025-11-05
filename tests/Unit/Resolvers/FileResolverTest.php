<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Resolvers\FileResolver;

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

test('it filters out non-php files', function (): void {
    $resolver = new FileResolver();
    $files = $resolver->getFiles([__DIR__.'/../../']);

    foreach ($files as $file) {
        expect($file->getExtension())->toBe('php');
    }
});

test('it handles single file path', function (): void {
    $resolver = new FileResolver();
    $files = $resolver->getFiles([__DIR__.'/../../Fixtures/ValidClass.php']);

    expect($files)->toHaveCount(1)
        ->and($files[0]->getFilename())->toBe('ValidClass.php');
});
