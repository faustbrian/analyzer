<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\AnalyzerServiceProvider;
use Cline\Analyzer\Console\Commands\AnalyzeCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;



test('it configures package with correct name', function (): void {
    // Arrange
    $package = new Package();
    $provider = new AnalyzerServiceProvider(app());

    // Act
    $provider->configurePackage($package);

    // Assert
    expect($package->name)->toBe('analyzer');
});

test('it registers config file', function (): void {
    // Arrange
    $package = new Package();
    $provider = new AnalyzerServiceProvider(app());

    // Act
    $provider->configurePackage($package);

    // Assert
    expect($package->configFileNames)->toContain('analyzer');
});

test('it registers analyze command', function (): void {
    // Arrange
    $package = new Package();
    $provider = new AnalyzerServiceProvider(app());

    // Act
    $provider->configurePackage($package);

    // Assert
    expect($package->commands)->toContain(AnalyzeCommand::class);
});

test('it configures package with all required components', function (): void {
    // Arrange
    $package = new Package();
    $provider = new AnalyzerServiceProvider(app());

    // Act
    $provider->configurePackage($package);

    // Assert
    expect($package->name)->toBe('analyzer')
        ->and($package->configFileNames)->toContain('analyzer')
        ->and($package->commands)->toContain(AnalyzeCommand::class);
});

test('it extends PackageServiceProvider', function (): void {
    // Arrange
    $provider = new AnalyzerServiceProvider(app());

    // Assert
    expect($provider)->toBeInstanceOf(PackageServiceProvider::class);
});
