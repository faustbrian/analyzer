<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Config\AnalyzerConfig;
use Cline\Analyzer\Processors\ParallelProcessor;
use Cline\Analyzer\Processors\SerialProcessor;
use Cline\Analyzer\Reporters\AgentReporter;
use Cline\Analyzer\Reporters\PromptsReporter;
use Cline\Analyzer\Resolvers\AnalysisResolver;
use Cline\Analyzer\Resolvers\FileResolver;
use Cline\Analyzer\Resolvers\PathResolver;
use Cline\Analyzer\Resolvers\RouteAnalysisResolver;
use Cline\Analyzer\Resolvers\TranslationAnalysisResolver;

test('it creates default config', function (): void {
    $config = AnalyzerConfig::make();

    expect($config)->toBeInstanceOf(AnalyzerConfig::class)
        ->and($config->workers)->toBe(0)
        ->and($config->paths)->toBeEmpty()
        ->and($config->ignore)->toBeEmpty()
        ->and($config->processor)->toBeInstanceOf(ParallelProcessor::class);
});

test('it sets paths', function (): void {
    $config = AnalyzerConfig::make()->paths(['src', 'tests']);

    expect($config->paths)->toBe(['src', 'tests']);
});

test('it configures worker count', function (): void {
    $config = AnalyzerConfig::make()->workers(8);

    expect($config->workers)->toBe(8)
        ->and($config->processor)->toBeInstanceOf(ParallelProcessor::class);
});

test('it can use serial processor', function (): void {
    $config = AnalyzerConfig::make()->processor(
        new SerialProcessor(),
    );

    expect($config->processor)->toBeInstanceOf(SerialProcessor::class);
});

test('it sets ignore patterns', function (): void {
    $config = AnalyzerConfig::make()->ignore(['Illuminate\\*', 'Symfony\\*']);

    expect($config->ignore)->toBe(['Illuminate\\*', 'Symfony\\*']);
});

test('it sets custom path resolver', function (): void {
    $resolver = new PathResolver();
    $config = AnalyzerConfig::make()->pathResolver($resolver);

    expect($config->pathResolver)->toBe($resolver);
});

test('it chains configuration methods', function (): void {
    $config = AnalyzerConfig::make()
        ->paths(['src'])
        ->workers(2)
        ->ignore(['Test\\*']);

    expect($config->paths)->toBe(['src'])
        ->and($config->workers)->toBe(2)
        ->and($config->ignore)->toBe(['Test\\*']);
});

test('it sets custom file resolver', function (): void {
    // Arrange
    $resolver = new FileResolver();

    // Act
    $config = AnalyzerConfig::make()->fileResolver($resolver);

    // Assert
    expect($config->fileResolver)->toBe($resolver);
});

test('it sets custom analysis resolver', function (): void {
    // Arrange
    $resolver = new AnalysisResolver(['Test\\*']);

    // Act
    $config = AnalyzerConfig::make()->analysisResolver($resolver);

    // Assert
    expect($config->analysisResolver)->toBe($resolver);
});

test('it sets custom reporter', function (): void {
    // Arrange
    $reporter = new AgentReporter();

    // Act
    $config = AnalyzerConfig::make()->reporter($reporter);

    // Assert
    expect($config->reporter)->toBe($reporter);
});

test('it sets custom processor', function (): void {
    // Arrange
    $processor = new SerialProcessor();

    // Act
    $config = AnalyzerConfig::make()->processor($processor);

    // Assert
    expect($config->processor)->toBe($processor);
});

test('it enables agent mode', function (): void {
    // Arrange
    $config = AnalyzerConfig::make();

    // Act
    $result = $config->agentMode();

    // Assert
    expect($result->reporter)->toBeInstanceOf(AgentReporter::class);
});

test('fileResolver returns new instance', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()->paths(['src']);
    $resolver = new FileResolver();

    // Act
    $newConfig = $config->fileResolver($resolver);

    // Assert
    expect($newConfig)->not->toBe($config)
        ->and($newConfig->fileResolver)->toBe($resolver)
        ->and($newConfig->paths)->toBe(['src']);
});

test('analysisResolver returns new instance', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()->paths(['src']);
    $resolver = new AnalysisResolver(['Test\\*']);

    // Act
    $newConfig = $config->analysisResolver($resolver);

    // Assert
    expect($newConfig)->not->toBe($config)
        ->and($newConfig->analysisResolver)->toBe($resolver)
        ->and($newConfig->paths)->toBe(['src']);
});

test('reporter returns new instance', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()->paths(['src']);
    $reporter = new AgentReporter();

    // Act
    $newConfig = $config->reporter($reporter);

    // Assert
    expect($newConfig)->not->toBe($config)
        ->and($newConfig->reporter)->toBe($reporter)
        ->and($newConfig->paths)->toBe(['src']);
});

test('processor returns new instance', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()->paths(['src']);
    $processor = new SerialProcessor();

    // Act
    $newConfig = $config->processor($processor);

    // Assert
    expect($newConfig)->not->toBe($config)
        ->and($newConfig->processor)->toBe($processor)
        ->and($newConfig->paths)->toBe(['src']);
});

test('agentMode returns new instance', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()->paths(['src']);

    // Act
    $newConfig = $config->agentMode();

    // Assert
    expect($newConfig)->not->toBe($config)
        ->and($newConfig->reporter)->toBeInstanceOf(AgentReporter::class)
        ->and($newConfig->paths)->toBe(['src']);
});

test('fileResolver preserves all other properties', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()
        ->paths(['src'])
        ->workers(4)
        ->ignore(['Test\\*']);

    $originalPathResolver = $config->pathResolver;
    $originalAnalysisResolver = $config->analysisResolver;
    $originalReporter = $config->reporter;
    $originalProcessor = $config->processor;

    $resolver = new FileResolver();

    // Act
    $newConfig = $config->fileResolver($resolver);

    // Assert
    expect($newConfig->paths)->toBe(['src'])
        ->and($newConfig->workers)->toBe(4)
        ->and($newConfig->ignore)->toBe(['Test\\*'])
        ->and($newConfig->pathResolver)->toBe($originalPathResolver)
        ->and($newConfig->fileResolver)->toBe($resolver)
        ->and($newConfig->analysisResolver)->toBe($originalAnalysisResolver)
        ->and($newConfig->reporter)->toBe($originalReporter)
        ->and($newConfig->processor)->toBe($originalProcessor);
});

test('analysisResolver preserves all other properties', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()
        ->paths(['src'])
        ->workers(4)
        ->ignore(['Test\\*']);

    $originalPathResolver = $config->pathResolver;
    $originalFileResolver = $config->fileResolver;
    $originalReporter = $config->reporter;
    $originalProcessor = $config->processor;

    $resolver = new AnalysisResolver(['Custom\\*']);

    // Act
    $newConfig = $config->analysisResolver($resolver);

    // Assert
    expect($newConfig->paths)->toBe(['src'])
        ->and($newConfig->workers)->toBe(4)
        ->and($newConfig->ignore)->toBe(['Test\\*'])
        ->and($newConfig->pathResolver)->toBe($originalPathResolver)
        ->and($newConfig->fileResolver)->toBe($originalFileResolver)
        ->and($newConfig->analysisResolver)->toBe($resolver)
        ->and($newConfig->reporter)->toBe($originalReporter)
        ->and($newConfig->processor)->toBe($originalProcessor);
});

test('reporter preserves all other properties', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()
        ->paths(['src'])
        ->workers(4)
        ->ignore(['Test\\*']);

    $originalPathResolver = $config->pathResolver;
    $originalFileResolver = $config->fileResolver;
    $originalAnalysisResolver = $config->analysisResolver;
    $originalProcessor = $config->processor;

    $reporter = new AgentReporter();

    // Act
    $newConfig = $config->reporter($reporter);

    // Assert
    expect($newConfig->paths)->toBe(['src'])
        ->and($newConfig->workers)->toBe(4)
        ->and($newConfig->ignore)->toBe(['Test\\*'])
        ->and($newConfig->pathResolver)->toBe($originalPathResolver)
        ->and($newConfig->fileResolver)->toBe($originalFileResolver)
        ->and($newConfig->analysisResolver)->toBe($originalAnalysisResolver)
        ->and($newConfig->reporter)->toBe($reporter)
        ->and($newConfig->processor)->toBe($originalProcessor);
});

test('processor preserves all other properties', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()
        ->paths(['src'])
        ->workers(4)
        ->ignore(['Test\\*']);

    $originalPathResolver = $config->pathResolver;
    $originalFileResolver = $config->fileResolver;
    $originalAnalysisResolver = $config->analysisResolver;
    $originalReporter = $config->reporter;

    $processor = new SerialProcessor();

    // Act
    $newConfig = $config->processor($processor);

    // Assert
    expect($newConfig->paths)->toBe(['src'])
        ->and($newConfig->workers)->toBe(4)
        ->and($newConfig->ignore)->toBe(['Test\\*'])
        ->and($newConfig->pathResolver)->toBe($originalPathResolver)
        ->and($newConfig->fileResolver)->toBe($originalFileResolver)
        ->and($newConfig->analysisResolver)->toBe($originalAnalysisResolver)
        ->and($newConfig->reporter)->toBe($originalReporter)
        ->and($newConfig->processor)->toBe($processor);
});

test('agentMode preserves all other properties', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()
        ->paths(['src'])
        ->workers(4)
        ->ignore(['Test\\*']);

    $originalPathResolver = $config->pathResolver;
    $originalFileResolver = $config->fileResolver;
    $originalAnalysisResolver = $config->analysisResolver;
    $originalProcessor = $config->processor;

    // Act
    $newConfig = $config->agentMode();

    // Assert
    expect($newConfig->paths)->toBe(['src'])
        ->and($newConfig->workers)->toBe(4)
        ->and($newConfig->ignore)->toBe(['Test\\*'])
        ->and($newConfig->pathResolver)->toBe($originalPathResolver)
        ->and($newConfig->fileResolver)->toBe($originalFileResolver)
        ->and($newConfig->analysisResolver)->toBe($originalAnalysisResolver)
        ->and($newConfig->reporter)->toBeInstanceOf(AgentReporter::class)
        ->and($newConfig->processor)->toBe($originalProcessor);
});

test('constructor sets default values correctly', function (): void {
    // Arrange & Act
    $config = new AnalyzerConfig();

    // Assert
    expect($config->paths)->toBe([])
        ->and($config->workers)->toBe(0)
        ->and($config->ignore)->toBe([])
        ->and($config->pathResolver)->toBeInstanceOf(PathResolver::class)
        ->and($config->fileResolver)->toBeInstanceOf(FileResolver::class)
        ->and($config->analysisResolver)->toBeInstanceOf(AnalysisResolver::class)
        ->and($config->reporter)->toBeInstanceOf(PromptsReporter::class)
        ->and($config->processor)->toBeInstanceOf(ParallelProcessor::class);
});

test('constructor accepts custom values', function (): void {
    // Arrange
    $paths = ['src', 'tests'];
    $workers = 8;
    $ignore = ['Test\\*', 'Mock\\*'];
    $pathResolver = new PathResolver();
    $fileResolver = new FileResolver();
    $analysisResolver = new AnalysisResolver(['Custom\\*']);
    $reporter = new AgentReporter();
    $processor = new SerialProcessor();

    // Act
    $config = new AnalyzerConfig(
        paths: $paths,
        workers: $workers,
        ignore: $ignore,
        pathResolver: $pathResolver,
        fileResolver: $fileResolver,
        analysisResolver: $analysisResolver,
        reporter: $reporter,
        processor: $processor,
    );

    // Assert
    expect($config->paths)->toBe($paths)
        ->and($config->workers)->toBe($workers)
        ->and($config->ignore)->toBe($ignore)
        ->and($config->pathResolver)->toBe($pathResolver)
        ->and($config->fileResolver)->toBe($fileResolver)
        ->and($config->analysisResolver)->toBe($analysisResolver)
        ->and($config->reporter)->toBe($reporter)
        ->and($config->processor)->toBe($processor);
});

test('routeAnalyzer configures route analysis resolver', function (): void {
    // Arrange
    $config = AnalyzerConfig::make();

    // Act
    $newConfig = $config->routeAnalyzer(
        routesPath: 'routes',
        cacheRoutes: false,
        cacheTtl: 7_200,
        reportDynamic: false,
        includePatterns: ['admin.*'],
        ignorePatterns: ['debug.*'],
    );

    // Assert
    expect($newConfig->analysisResolver)->toBeInstanceOf(RouteAnalysisResolver::class);
});

test('routeAnalyzer uses default values', function (): void {
    // Arrange
    $config = AnalyzerConfig::make();

    // Act
    $newConfig = $config->routeAnalyzer(routesPath: 'routes');

    // Assert
    expect($newConfig->analysisResolver)->toBeInstanceOf(RouteAnalysisResolver::class);
});

test('routeAnalyzer returns new instance', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()->paths(['src']);

    // Act
    $newConfig = $config->routeAnalyzer(routesPath: 'routes');

    // Assert
    expect($newConfig)->not->toBe($config)
        ->and($newConfig->analysisResolver)->toBeInstanceOf(RouteAnalysisResolver::class)
        ->and($newConfig->paths)->toBe(['src']);
});

test('routeAnalyzer preserves all other properties', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()
        ->paths(['src'])
        ->workers(4)
        ->ignore(['Test\\*']);

    $originalPathResolver = $config->pathResolver;
    $originalFileResolver = $config->fileResolver;
    $originalReporter = $config->reporter;
    $originalProcessor = $config->processor;

    // Act
    $newConfig = $config->routeAnalyzer(routesPath: 'routes');

    // Assert
    expect($newConfig->paths)->toBe(['src'])
        ->and($newConfig->workers)->toBe(4)
        ->and($newConfig->ignore)->toBe(['Test\\*'])
        ->and($newConfig->pathResolver)->toBe($originalPathResolver)
        ->and($newConfig->fileResolver)->toBe($originalFileResolver)
        ->and($newConfig->reporter)->toBe($originalReporter)
        ->and($newConfig->processor)->toBe($originalProcessor);
});

test('translationAnalyzer configures translation analysis resolver', function (): void {
    // Arrange
    $config = AnalyzerConfig::make();

    // Act
    $newConfig = $config->translationAnalyzer(
        langPath: 'lang',
        locales: ['en', 'es'],
        reportDynamic: false,
        vendorPath: 'vendor',
        ignore: ['temp.*'],
        includePatterns: ['validation.*'],
    );

    // Assert
    expect($newConfig->analysisResolver)->toBeInstanceOf(TranslationAnalysisResolver::class);
});

test('translationAnalyzer uses default values', function (): void {
    // Arrange
    $config = AnalyzerConfig::make();

    // Act
    $newConfig = $config->translationAnalyzer(langPath: 'lang');

    // Assert
    expect($newConfig->analysisResolver)->toBeInstanceOf(TranslationAnalysisResolver::class);
});

test('translationAnalyzer returns new instance', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()->paths(['src']);

    // Act
    $newConfig = $config->translationAnalyzer(langPath: 'lang');

    // Assert
    expect($newConfig)->not->toBe($config)
        ->and($newConfig->analysisResolver)->toBeInstanceOf(TranslationAnalysisResolver::class)
        ->and($newConfig->paths)->toBe(['src']);
});

test('translationAnalyzer preserves all other properties', function (): void {
    // Arrange
    $config = AnalyzerConfig::make()
        ->paths(['src'])
        ->workers(4)
        ->ignore(['Test\\*']);

    $originalPathResolver = $config->pathResolver;
    $originalFileResolver = $config->fileResolver;
    $originalReporter = $config->reporter;
    $originalProcessor = $config->processor;

    // Act
    $newConfig = $config->translationAnalyzer(langPath: 'lang');

    // Assert
    expect($newConfig->paths)->toBe(['src'])
        ->and($newConfig->workers)->toBe(4)
        ->and($newConfig->ignore)->toBe(['Test\\*'])
        ->and($newConfig->pathResolver)->toBe($originalPathResolver)
        ->and($newConfig->fileResolver)->toBe($originalFileResolver)
        ->and($newConfig->reporter)->toBe($originalReporter)
        ->and($newConfig->processor)->toBe($originalProcessor);
});
