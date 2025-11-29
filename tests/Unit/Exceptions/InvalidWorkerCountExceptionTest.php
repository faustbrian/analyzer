<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Exceptions\InvalidWorkerCountException;

test('create returns exception with formatted message', function (): void {
    $exception = InvalidWorkerCountException::create(0);

    expect($exception)
        ->toBeInstanceOf(InvalidWorkerCountException::class)
        ->getMessage()->toBe('Workers must be at least 1, got 0.');
});

test('create accepts negative worker count', function (): void {
    $exception = InvalidWorkerCountException::create(-5);

    expect($exception->getMessage())->toBe('Workers must be at least 1, got -5.');
});
