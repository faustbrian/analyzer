<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer;

use Cline\Analyzer\Console\Commands\AnalyzeCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Laravel service provider for the Analyzer package.
 *
 * Registers the analyzer command and publishes configuration files for
 * Laravel applications using Spatie's package tools. Enables integration
 * with Laravel's command system and dependency injection container.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AnalyzerServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the analyzer package registration and publishing.
     *
     * Registers the analyzer command with Artisan, publishes the configuration file
     * for customization, and sets the package name for Laravel's service discovery.
     * Called automatically by Laravel's package discovery system during bootstrap.
     *
     * @param Package $package Package configuration instance from Spatie Package Tools
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('analyzer')
            ->hasConfigFile()
            ->hasCommand(AnalyzeCommand::class);
    }
}
