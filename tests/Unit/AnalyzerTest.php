<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;
use Cline\Analyzer\Contracts\AnalysisResolverInterface;
use Cline\Analyzer\Contracts\FileResolverInterface;
use Cline\Analyzer\Contracts\PathResolverInterface;
use Cline\Analyzer\Contracts\ProcessorInterface;
use Cline\Analyzer\Contracts\ReporterInterface;
use Cline\Analyzer\Data\AnalysisResult;
use Cline\Analyzer\Enums\Verbosity;

beforeEach(function (): void {
    $this->file = new SplFileInfo(__FILE__);

    $this->pathResolver = Mockery::mock(PathResolverInterface::class);
    $this->fileResolver = Mockery::mock(FileResolverInterface::class);
    $this->analysisResolver = Mockery::mock(AnalysisResolverInterface::class);
    $this->reporter = Mockery::mock(ReporterInterface::class);
    $this->processor = Mockery::mock(ProcessorInterface::class);
});

afterEach(function (): void {
    Mockery::close();
});

test('analyze returns results from processor', function (): void {
    // Arrange
    $paths = ['/path/to/src'];
    $files = [$this->file];
    $expectedResults = [
        AnalysisResult::success($this->file, ['App\\User']),
    ];

    $this->pathResolver->shouldReceive('resolve')
        ->once()
        ->with($paths)
        ->andReturn($paths);

    $this->fileResolver->shouldReceive('getFiles')
        ->once()
        ->with($paths)
        ->andReturn($files);

    $this->reporter->shouldReceive('start')
        ->once()
        ->with($files);

    $this->analysisResolver->shouldReceive('analyze')
        ->once()
        ->with($this->file)
        ->andReturn($expectedResults[0]);

    $this->reporter->shouldReceive('progress')
        ->once()
        ->with($expectedResults[0]);

    $this->processor->shouldReceive('process')
        ->once()
        ->andReturnUsing(fn ($files, $callback): array => array_map($callback, $files));

    $this->reporter->shouldReceive('finish')
        ->once()
        ->with($expectedResults);

    $config = new AnalyzerConfig(
        paths: $paths,
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act
    $results = $analyzer->analyze();

    // Assert
    expect($results)->toBe($expectedResults)
        ->and($results)->toHaveCount(1)
        ->and($results[0])->toBeInstanceOf(AnalysisResult::class);
});

test('analyze handles exception during analysis with normal verbosity', function (): void {
    // Arrange
    $paths = ['/path/to/src'];
    $files = [$this->file];
    $exception = new RuntimeException('Analysis failed');

    $this->pathResolver->shouldReceive('resolve')
        ->once()
        ->with($paths)
        ->andReturn($paths);

    $this->fileResolver->shouldReceive('getFiles')
        ->once()
        ->with($paths)
        ->andReturn($files);

    $this->reporter->shouldReceive('start')
        ->once()
        ->with($files);

    $this->analysisResolver->shouldReceive('analyze')
        ->once()
        ->with($this->file)
        ->andThrow($exception);

    $this->reporter->shouldReceive('progress')
        ->once()
        ->with(Mockery::on(fn ($result): bool => $result instanceof AnalysisResult
            && $result->hasError()
            && $result->error === 'Analysis failed'
            && !str_contains($result->error, 'Stack trace:')));

    $this->processor->shouldReceive('process')
        ->once()
        ->andReturnUsing(fn ($files, $callback): array => array_map($callback, $files));

    $this->reporter->shouldReceive('finish')
        ->once();

    $config = new AnalyzerConfig(
        paths: $paths,
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
        verbosity: Verbosity::Normal,
    );

    $analyzer = new Analyzer($config);

    // Act
    $results = $analyzer->analyze();

    // Assert
    expect($results)->toHaveCount(1)
        ->and($results[0])->toBeInstanceOf(AnalysisResult::class)
        ->and($results[0]->hasError())->toBeTrue()
        ->and($results[0]->error)->toBe('Analysis failed')
        ->and($results[0]->error)->not->toContain('Stack trace:');
});

test('analyze handles exception during analysis with debug verbosity and includes stack trace', function (): void {
    // Arrange
    $paths = ['/path/to/src'];
    $files = [$this->file];
    $exception = new RuntimeException('Analysis failed');

    $this->pathResolver->shouldReceive('resolve')
        ->once()
        ->with($paths)
        ->andReturn($paths);

    $this->fileResolver->shouldReceive('getFiles')
        ->once()
        ->with($paths)
        ->andReturn($files);

    $this->reporter->shouldReceive('start')
        ->once()
        ->with($files);

    $this->analysisResolver->shouldReceive('analyze')
        ->once()
        ->with($this->file)
        ->andThrow($exception);

    $this->reporter->shouldReceive('progress')
        ->once()
        ->with(Mockery::on(fn ($result): bool => $result instanceof AnalysisResult
            && $result->hasError()
            && str_contains((string) $result->error, 'Analysis failed')
            && str_contains((string) $result->error, 'Stack trace:')));

    $this->processor->shouldReceive('process')
        ->once()
        ->andReturnUsing(fn ($files, $callback): array => array_map($callback, $files));

    $this->reporter->shouldReceive('finish')
        ->once();

    $config = new AnalyzerConfig(
        paths: $paths,
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
        verbosity: Verbosity::Debug,
    );

    $analyzer = new Analyzer($config);

    // Act
    $results = $analyzer->analyze();

    // Assert
    expect($results)->toHaveCount(1)
        ->and($results[0])->toBeInstanceOf(AnalysisResult::class)
        ->and($results[0]->hasError())->toBeTrue()
        ->and($results[0]->error)->toContain('Analysis failed')
        ->and($results[0]->error)->toContain('Stack trace:')
        ->and($results[0]->error)->toContain("\n\nStack trace:\n");
});

test('analyze handles exception with verbose verbosity without stack trace', function (): void {
    // Arrange
    $paths = ['/path/to/src'];
    $files = [$this->file];
    $exception = new RuntimeException('Verbose error');

    $this->pathResolver->shouldReceive('resolve')
        ->once()
        ->with($paths)
        ->andReturn($paths);

    $this->fileResolver->shouldReceive('getFiles')
        ->once()
        ->with($paths)
        ->andReturn($files);

    $this->reporter->shouldReceive('start')
        ->once()
        ->with($files);

    $this->analysisResolver->shouldReceive('analyze')
        ->once()
        ->with($this->file)
        ->andThrow($exception);

    $this->reporter->shouldReceive('progress')
        ->once()
        ->with(Mockery::on(fn ($result): bool => $result instanceof AnalysisResult
            && $result->hasError()
            && $result->error === 'Verbose error'));

    $this->processor->shouldReceive('process')
        ->once()
        ->andReturnUsing(fn ($files, $callback): array => array_map($callback, $files));

    $this->reporter->shouldReceive('finish')
        ->once();

    $config = new AnalyzerConfig(
        paths: $paths,
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
        verbosity: Verbosity::Verbose,
    );

    $analyzer = new Analyzer($config);

    // Act
    $results = $analyzer->analyze();

    // Assert
    expect($results)->toHaveCount(1)
        ->and($results[0]->error)->toBe('Verbose error')
        ->and($results[0]->error)->not->toContain('Stack trace:');
});

test('analyze handles exception with very verbose verbosity without stack trace', function (): void {
    // Arrange
    $paths = ['/path/to/src'];
    $files = [$this->file];
    $exception = new RuntimeException('Very verbose error');

    $this->pathResolver->shouldReceive('resolve')
        ->once()
        ->with($paths)
        ->andReturn($paths);

    $this->fileResolver->shouldReceive('getFiles')
        ->once()
        ->with($paths)
        ->andReturn($files);

    $this->reporter->shouldReceive('start')
        ->once()
        ->with($files);

    $this->analysisResolver->shouldReceive('analyze')
        ->once()
        ->with($this->file)
        ->andThrow($exception);

    $this->reporter->shouldReceive('progress')
        ->once()
        ->with(Mockery::on(fn ($result): bool => $result instanceof AnalysisResult
            && $result->hasError()
            && $result->error === 'Very verbose error'));

    $this->processor->shouldReceive('process')
        ->once()
        ->andReturnUsing(fn ($files, $callback): array => array_map($callback, $files));

    $this->reporter->shouldReceive('finish')
        ->once();

    $config = new AnalyzerConfig(
        paths: $paths,
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
        verbosity: Verbosity::VeryVerbose,
    );

    $analyzer = new Analyzer($config);

    // Act
    $results = $analyzer->analyze();

    // Assert
    expect($results)->toHaveCount(1)
        ->and($results[0]->error)->toBe('Very verbose error')
        ->and($results[0]->error)->not->toContain('Stack trace:');
});

test('analyze throws RuntimeException when path resolver is not configured', function (): void {
    // Arrange
    $config = new AnalyzerConfig(
        paths: ['/path/to/src'],
        pathResolver: null,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act & Assert
    expect(fn (): array => $analyzer->analyze())
        ->toThrow(RuntimeException::class, 'Required resolvers and reporter must be configured');
});

test('analyze throws RuntimeException when reporter is not configured', function (): void {
    // Arrange
    $config = new AnalyzerConfig(
        paths: ['/path/to/src'],
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: null,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act & Assert
    expect(fn (): array => $analyzer->analyze())
        ->toThrow(RuntimeException::class, 'Required resolvers and reporter must be configured');
});

test('hasFailures returns true when results contain failures', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::success($file, ['App\\User']),
        AnalysisResult::failure($file, ['App\\User', 'App\\Missing'], ['App\\Missing']),
        AnalysisResult::success($file, ['App\\Post']),
    ];

    $config = new AnalyzerConfig(
        paths: ['/path/to/src'],
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act
    $hasFailures = $analyzer->hasFailures($results);

    // Assert
    expect($hasFailures)->toBeTrue();
});

test('hasFailures returns true when results contain errors', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::success($file, ['App\\User']),
        AnalysisResult::error($file, 'Parse error'),
        AnalysisResult::success($file, ['App\\Post']),
    ];

    $config = new AnalyzerConfig(
        paths: ['/path/to/src'],
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act
    $hasFailures = $analyzer->hasFailures($results);

    // Assert
    expect($hasFailures)->toBeTrue();
});

test('hasFailures returns false when all results are successful', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::success($file, ['App\\User']),
        AnalysisResult::success($file, ['App\\Post']),
        AnalysisResult::success($file, ['App\\Comment']),
    ];

    $config = new AnalyzerConfig(
        paths: ['/path/to/src'],
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act
    $hasFailures = $analyzer->hasFailures($results);

    // Assert
    expect($hasFailures)->toBeFalse();
});

test('hasFailures returns false when results array is empty', function (): void {
    // Arrange
    $results = [];

    $config = new AnalyzerConfig(
        paths: ['/path/to/src'],
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act
    $hasFailures = $analyzer->hasFailures($results);

    // Assert
    expect($hasFailures)->toBeFalse();
});

test('analyze processes multiple files correctly', function (): void {
    // Arrange
    $paths = ['/path/to/src'];
    $file1 = new SplFileInfo(__FILE__);
    $file2 = new SplFileInfo(__DIR__);
    $files = [$file1, $file2];

    $result1 = AnalysisResult::success($file1, ['App\\User']);
    $result2 = AnalysisResult::failure($file2, ['App\\Post', 'App\\Missing'], ['App\\Missing']);

    $expectedResults = [$result1, $result2];

    $this->pathResolver->shouldReceive('resolve')
        ->once()
        ->with($paths)
        ->andReturn($paths);

    $this->fileResolver->shouldReceive('getFiles')
        ->once()
        ->with($paths)
        ->andReturn($files);

    $this->reporter->shouldReceive('start')
        ->once()
        ->with($files);

    $this->analysisResolver->shouldReceive('analyze')
        ->once()
        ->with($file1)
        ->andReturn($result1);

    $this->analysisResolver->shouldReceive('analyze')
        ->once()
        ->with($file2)
        ->andReturn($result2);

    $this->reporter->shouldReceive('progress')
        ->once()
        ->with($result1);

    $this->reporter->shouldReceive('progress')
        ->once()
        ->with($result2);

    $this->processor->shouldReceive('process')
        ->once()
        ->andReturnUsing(fn ($files, $callback): array => array_map($callback, $files));

    $this->reporter->shouldReceive('finish')
        ->once()
        ->with($expectedResults);

    $config = new AnalyzerConfig(
        paths: $paths,
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act
    $results = $analyzer->analyze();

    // Assert
    expect($results)->toBe($expectedResults)
        ->and($results)->toHaveCount(2);
});

test('analyze creates error result with file when exception is thrown', function (): void {
    // Arrange
    $paths = ['/path/to/src'];
    $files = [$this->file];
    $exception = new Exception('Custom exception message');

    $this->pathResolver->shouldReceive('resolve')
        ->once()
        ->with($paths)
        ->andReturn($paths);

    $this->fileResolver->shouldReceive('getFiles')
        ->once()
        ->with($paths)
        ->andReturn($files);

    $this->reporter->shouldReceive('start')
        ->once()
        ->with($files);

    $this->analysisResolver->shouldReceive('analyze')
        ->once()
        ->with($this->file)
        ->andThrow($exception);

    $this->reporter->shouldReceive('progress')
        ->once()
        ->with(Mockery::on(fn ($result): bool => $result instanceof AnalysisResult
            && $result->file === $files[0]
            && $result->hasError()
            && $result->error === 'Custom exception message'));

    $this->processor->shouldReceive('process')
        ->once()
        ->andReturnUsing(fn ($files, $callback): array => array_map($callback, $files));

    $this->reporter->shouldReceive('finish')
        ->once();

    $config = new AnalyzerConfig(
        paths: $paths,
        pathResolver: $this->pathResolver,
        fileResolver: $this->fileResolver,
        analysisResolver: $this->analysisResolver,
        reporter: $this->reporter,
        processor: $this->processor,
    );

    $analyzer = new Analyzer($config);

    // Act
    $results = $analyzer->analyze();

    // Assert
    expect($results)->toHaveCount(1)
        ->and($results[0]->file)->toBe($this->file)
        ->and($results[0]->hasError())->toBeTrue()
        ->and($results[0]->error)->toBe('Custom exception message');
});
