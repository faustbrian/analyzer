<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Actions\DetectCoreCount;
use Illuminate\Support\Facades\Process;

test('it detects CPU core count', function (): void {
    $action = new DetectCoreCount();
    $cores = $action();

    expect($cores)->toBeInt()
        ->and($cores)->toBeGreaterThanOrEqual(1)
        ->and($cores)->toBeLessThanOrEqual(256); // Reasonable upper bound
});

test('it returns at least 1 core', function (): void {
    $action = new DetectCoreCount();
    $cores = $action();

    expect($cores)->toBeGreaterThanOrEqual(1);
});

test('it returns fallback value of 4 when process fails', function (): void {
    // Arrange
    Process::fake([
        '*' => Process::result(errorOutput: 'Command failed', exitCode: 1),
    ]);

    $action = new DetectCoreCount();

    // Act
    $cores = $action();

    // Assert
    expect($cores)->toBe(4);
});

test('it returns minimum of 1 core even when process returns 0', function (): void {
    // Arrange
    Process::fake([
        '*' => Process::result(output: '0'),
    ]);

    $action = new DetectCoreCount();

    // Act
    $cores = $action();

    // Assert
    expect($cores)->toBe(1);
});

test('it returns minimum of 1 core even when process returns negative', function (): void {
    // Arrange
    Process::fake([
        '*' => Process::result(output: '-5'),
    ]);

    $action = new DetectCoreCount();

    // Act
    $cores = $action();

    // Assert
    expect($cores)->toBe(1);
});
