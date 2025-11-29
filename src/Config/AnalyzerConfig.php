<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Config;

use Cline\Analyzer\Actions\DetectCoreCount;
use Cline\Analyzer\Contracts\AnalysisResolverInterface;
use Cline\Analyzer\Contracts\FileResolverInterface;
use Cline\Analyzer\Contracts\PathResolverInterface;
use Cline\Analyzer\Contracts\ProcessorInterface;
use Cline\Analyzer\Contracts\ReporterInterface;
use Cline\Analyzer\Enums\Verbosity;
use Cline\Analyzer\Processors\ParallelProcessor;
use Cline\Analyzer\Reporters\AgentReporter;
use Cline\Analyzer\Reporters\PromptsReporter;
use Cline\Analyzer\Resolvers\AnalysisResolver;
use Cline\Analyzer\Resolvers\FileResolver;
use Cline\Analyzer\Resolvers\PathResolver;
use Cline\Analyzer\Resolvers\RouteAnalysisResolver;
use Cline\Analyzer\Resolvers\TranslationAnalysisResolver;

/**
 * Immutable configuration for the analyzer with fluent builder interface.
 *
 * Provides a fluent API for configuring analyzer behavior including paths to analyze,
 * parallelization settings, ignore patterns, and custom resolver implementations. Each
 * configuration method returns a new instance, maintaining immutability throughout the
 * configuration chain. Supports dependency injection for all resolver components while
 * providing sensible defaults for rapid setup.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class AnalyzerConfig
{
    /**
     * Resolver for discovering and filtering files to analyze.
     *
     * Defaults to FileResolver instance configured with exclude patterns.
     */
    public FileResolverInterface $fileResolver;

    /**
     * Resolver for analyzing files and validating class references.
     *
     * Defaults to AnalysisResolver instance configured with ignore patterns.
     */
    public AnalysisResolverInterface $analysisResolver;

    /**
     * Processor for executing file analysis (serial or parallel).
     *
     * Defaults to ParallelProcessor with auto-detected core count or specified workers.
     */
    public ProcessorInterface $processor;

    /**
     * Create a new analyzer configuration instance.
     *
     * Initializes the analyzer configuration with sensible defaults for all components.
     * Non-promoted parameters (fileResolver, analysisResolver, processor) are initialized
     * in the constructor body to allow dependency on promoted properties and runtime logic.
     *
     * @param array<string>                  $paths            Paths to analyze (files or directories). Relative paths are
     *                                                         resolved from the application base path. Empty array performs
     *                                                         no analysis until paths are configured via fluent methods.
     * @param int                            $workers          Number of parallel worker processes for ParallelProcessor.
     *                                                         When 0 or not specified, automatically detects CPU core count
     *                                                         for optimal parallelization. Higher values increase throughput
     *                                                         but consume more memory. Applies only to ParallelProcessor.
     * @param array<string>                  $ignore           Glob patterns for fully qualified class names to ignore during
     *                                                         analysis. Useful for excluding framework classes, vendor packages,
     *                                                         or dynamically loaded classes that are available at runtime but
     *                                                         cannot be resolved statically. Supports wildcards like 'Illuminate\*'.
     * @param array<string>                  $exclude          Glob patterns for files and directories to exclude from scanning.
     *                                                         Prevents analysis of vendor folders, build artifacts, cache directories,
     *                                                         or other resources that should not be scanned. Patterns match against
     *                                                         both file names and directory paths. Supports wildcards.
     * @param null|PathResolverInterface     $pathResolver     Custom path resolver implementation for converting relative paths
     *                                                         to absolute paths and ensuring consistent path formatting across
     *                                                         operating systems. Defaults to PathResolver instance when not provided.
     * @param null|FileResolverInterface     $fileResolver     Custom file resolver implementation for discovering PHP files within
     *                                                         configured paths. When null, defaults to FileResolver configured with
     *                                                         exclude patterns from $exclude parameter. Handles recursive directory
     *                                                         scanning and file filtering.
     * @param null|AnalysisResolverInterface $analysisResolver Custom analysis resolver implementation for examining PHP files to
     *                                                         identify class imports and validate their existence. When null, defaults
     *                                                         to AnalysisResolver configured with ignore patterns from $ignore parameter.
     *                                                         This is the core analysis logic.
     * @param null|ReporterInterface         $reporter         Custom reporter implementation for displaying analysis progress and
     *                                                         results. Handles formatting output, progress indicators, and issue
     *                                                         presentation. Defaults to PromptsReporter for interactive console
     *                                                         experience using Laravel Prompts.
     * @param null|ProcessorInterface        $processor        Custom processor implementation for executing file analysis. When null,
     *                                                         defaults to ParallelProcessor with worker count from $workers parameter
     *                                                         (or auto-detected CPU cores if workers is 0). Controls serial vs parallel
     *                                                         execution strategy.
     * @param Verbosity                      $verbosity        Output verbosity level controlling detail of analyzer output.
     *                                                         Higher levels show more details like stack traces for errors.
     */
    public function __construct(
        public array $paths = [],
        public int $workers = 0,
        public array $ignore = [],
        public array $exclude = [],
        public ?PathResolverInterface $pathResolver = new PathResolver(),
        ?FileResolverInterface $fileResolver = null,
        ?AnalysisResolverInterface $analysisResolver = null,
        public ?ReporterInterface $reporter = new PromptsReporter(),
        ?ProcessorInterface $processor = null,
        public Verbosity $verbosity = Verbosity::Normal,
    ) {
        $this->fileResolver = $fileResolver ?? new FileResolver($this->exclude);
        $this->analysisResolver = $analysisResolver ?? new AnalysisResolver($this->ignore);
        $workerCount = $this->workers > 0 ? $this->workers : DetectCoreCount::handle();
        $this->processor = $processor ?? new ParallelProcessor($workerCount);
    }

    /**
     * Create a new configuration instance with default settings.
     *
     * Factory method that provides a starting point for fluent configuration.
     * Returns a config with empty paths, parallel processing enabled, 4 workers,
     * and default resolver implementations.
     *
     * @return self New configuration instance with defaults
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Configure paths to analyze.
     *
     * Returns a new configuration instance with the specified paths. Accepts both
     * file paths and directory paths. Directories will be recursively scanned for
     * PHP files during analysis.
     *
     * @param  array<string> $paths File or directory paths to analyze
     * @return self          New configuration instance with updated paths
     */
    public function paths(array $paths): self
    {
        return new self(
            paths: $paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure worker count for parallel processing.
     *
     * Returns a new configuration instance with updated worker count. This
     * controls how many parallel processes will be used when ParallelProcessor
     * is configured as the processor implementation.
     *
     * @param  int  $workers Number of parallel worker processes to use
     * @return self New configuration instance with updated worker count
     */
    public function workers(int $workers): self
    {
        return new self(
            paths: $this->paths,
            workers: $workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: new ParallelProcessor($workers),
        );
    }

    /**
     * Configure class name patterns to ignore during analysis.
     *
     * Returns a new configuration instance with updated ignore patterns. Patterns
     * are matched against fully qualified class names using glob syntax. Useful for
     * excluding known missing classes or third-party references.
     *
     * @param  array<string> $patterns Glob patterns for class names to ignore
     * @return self          New configuration instance with updated ignore patterns
     */
    public function ignore(array $patterns): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $patterns,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: new AnalysisResolver($patterns),
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure file and directory patterns to exclude from scanning.
     *
     * Returns a new configuration instance with updated exclude patterns. Patterns
     * are matched against file and directory paths using glob syntax. Useful for
     * skipping vendor directories, build artifacts, or cache folders.
     *
     * @param  array<string> $patterns Glob patterns for files and directories to exclude
     * @return self          New configuration instance with updated exclude patterns
     */
    public function exclude(array $patterns): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $patterns,
            pathResolver: $this->pathResolver,
            fileResolver: new FileResolver($patterns),
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure custom path resolver implementation.
     *
     * Returns a new configuration instance with a custom path resolver. Allows
     * overriding default path resolution behavior for specialized use cases.
     *
     * @param  PathResolverInterface $resolver Custom path resolver implementation
     * @return self                  New configuration instance with custom path resolver
     */
    public function pathResolver(PathResolverInterface $resolver): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $resolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure custom file resolver implementation.
     *
     * Returns a new configuration instance with a custom file resolver. Allows
     * overriding default file discovery and filtering logic.
     *
     * @param  FileResolverInterface $resolver Custom file resolver implementation
     * @return self                  New configuration instance with custom file resolver
     */
    public function fileResolver(FileResolverInterface $resolver): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $resolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure custom analysis resolver implementation.
     *
     * Returns a new configuration instance with a custom analysis resolver. Allows
     * overriding default class reference extraction and validation logic.
     *
     * @param  AnalysisResolverInterface $resolver Custom analysis resolver implementation
     * @return self                      New configuration instance with custom analysis resolver
     */
    public function analysisResolver(AnalysisResolverInterface $resolver): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $resolver,
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure custom reporter implementation.
     *
     * Returns a new configuration instance with a custom reporter. Allows
     * overriding default progress and result reporting behavior.
     *
     * @param  ReporterInterface $reporter Custom reporter implementation
     * @return self              New configuration instance with custom reporter
     */
    public function reporter(ReporterInterface $reporter): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure custom processor implementation.
     *
     * Returns a new configuration instance with a custom processor. Allows
     * overriding default serial/parallel execution logic.
     *
     * @param  ProcessorInterface $processor Custom processor implementation
     * @return self               New configuration instance with custom processor
     */
    public function processor(ProcessorInterface $processor): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $processor,
        );
    }

    /**
     * Configure agent-optimized output for parallel fixing.
     *
     * Returns a new configuration instance with the AgentReporter, which
     * generates XML-structured prompts for spawning parallel agents to fix
     * missing class reference issues efficiently.
     *
     * @return self New configuration instance with agent reporter
     */
    public function agentMode(): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: new AgentReporter(),
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure output verbosity level.
     *
     * Returns a new configuration instance with updated verbosity. Higher verbosity
     * levels provide more detailed output like stack traces for errors.
     *
     * @param  Verbosity $level Verbosity level enum value
     * @return self      New configuration instance with updated verbosity
     */
    public function verbosity(Verbosity $level): self
    {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $level,
        );
    }

    /**
     * Configure route analysis resolver.
     *
     * Returns a new configuration instance with a RouteAnalysisResolver configured
     * with the specified settings for route name validation.
     *
     * @param  string             $routesPath      Path to Laravel routes directory
     * @param  bool               $cacheRoutes     Enable route caching for performance (default: true)
     * @param  int                $cacheTtl        Cache TTL in seconds (default: 3600)
     * @param  bool               $reportDynamic   Report dynamic route names as warnings (default: true)
     * @param  null|array<string> $includePatterns Only validate routes matching these patterns
     * @param  null|array<string> $ignorePatterns  Ignore routes matching these patterns
     * @return self               New configuration instance with route analyzer
     */
    public function routeAnalyzer(
        string $routesPath,
        bool $cacheRoutes = true,
        int $cacheTtl = 3_600,
        bool $reportDynamic = true,
        ?array $includePatterns = null,
        ?array $ignorePatterns = null,
    ): self {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: new RouteAnalysisResolver(
                routesPath: $routesPath,
                cacheRoutes: $cacheRoutes,
                cacheTtl: $cacheTtl,
                reportDynamic: $reportDynamic,
                includePatterns: $includePatterns,
                ignorePatterns: $ignorePatterns,
            ),
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }

    /**
     * Configure translation analysis resolver.
     *
     * Returns a new configuration instance with a TranslationAnalysisResolver configured
     * with the specified settings for translation key validation.
     *
     * @param  string             $langPath        Path to Laravel lang directory
     * @param  array<string>      $locales         Locale codes to validate against (default: ['en'])
     * @param  bool               $reportDynamic   Report dynamic translation keys as warnings (default: true)
     * @param  null|string        $vendorPath      Path to vendor package translations
     * @param  array<string>      $ignore          Translation key patterns to ignore
     * @param  null|array<string> $includePatterns Only validate keys matching these patterns
     * @return self               New configuration instance with translation analyzer
     */
    public function translationAnalyzer(
        string $langPath,
        array $locales = ['en'],
        bool $reportDynamic = true,
        ?string $vendorPath = null,
        array $ignore = [],
        ?array $includePatterns = null,
    ): self {
        return new self(
            paths: $this->paths,
            workers: $this->workers,
            ignore: $this->ignore,
            exclude: $this->exclude,
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: new TranslationAnalysisResolver(
                langPath: $langPath,
                locales: $locales,
                reportDynamic: $reportDynamic,
                vendorPath: $vendorPath,
                ignore: $ignore,
                includePatterns: $includePatterns,
            ),
            reporter: $this->reporter,
            processor: $this->processor,
            verbosity: $this->verbosity,
        );
    }
}
