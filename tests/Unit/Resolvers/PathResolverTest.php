<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Resolvers\PathResolver;

test('it filters out non-existent paths', function (): void {
    $resolver = new PathResolver();
    $paths = [
        __DIR__,
        '/non/existent/path',
        __FILE__,
    ];

    $resolved = $resolver->resolve($paths);

    expect($resolved)->toBeArray()
        ->and($resolved)->toHaveCount(2)
        ->and($resolved)->toContain(__DIR__)
        ->and($resolved)->toContain(__FILE__)
        ->and($resolved)->not->toContain('/non/existent/path');
});

test('it handles empty paths array', function (): void {
    $resolver = new PathResolver();
    $resolved = $resolver->resolve([]);

    expect($resolved)->toBeEmpty();
});
