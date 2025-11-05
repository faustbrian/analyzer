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
use Cline\Analyzer\Processors\ParallelProcessor;
use Cline\Analyzer\Reporters\AgentReporter;
use Cline\Analyzer\Reporters\PromptsReporter;
use Cline\Analyzer\Resolvers\AnalysisResolver;
use Cline\Analyzer\Resolvers\FileResolver;
use Cline\Analyzer\Resolvers\PathResolver;

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
     * Resolver for analyzing files and validating class references.
     */
    public AnalysisResolverInterface $analysisResolver;

    /**
     * Processor for executing file analysis (serial or parallel).
     */
    public ProcessorInterface $processor;

    /**
     * Create a new analyzer configuration instance.
     *
     * @param array<string>                  $paths            Paths to analyze (files or directories). Defaults to empty array.
     * @param int                            $workers          Number of parallel worker processes for ParallelProcessor. Defaults to auto-detected core count.
     * @param array<string>                  $ignore           Glob patterns for class names to ignore during analysis. Defaults to empty array.
     * @param null|PathResolverInterface     $pathResolver     Custom path resolver. Defaults to PathResolver instance.
     * @param null|FileResolverInterface     $fileResolver     Custom file resolver. Defaults to FileResolver instance.
     * @param null|AnalysisResolverInterface $analysisResolver Custom analysis resolver. Defaults to AnalysisResolver with ignore patterns.
     * @param null|ReporterInterface         $reporter         Custom reporter. Defaults to PromptsReporter instance.
     * @param null|ProcessorInterface        $processor        Custom processor. Defaults to ParallelProcessor with configured workers.
     */
    public function __construct(
        public array $paths = [],
        public int $workers = 0,
        public array $ignore = [],
        public ?PathResolverInterface $pathResolver = new PathResolver(),
        public ?FileResolverInterface $fileResolver = new FileResolver(),
        ?AnalysisResolverInterface $analysisResolver = null,
        public ?ReporterInterface $reporter = new PromptsReporter(),
        ?ProcessorInterface $processor = null,
    ) {
        $this->analysisResolver = $analysisResolver ?? new AnalysisResolver($this->ignore);
        $workerCount = $this->workers > 0 ? $this->workers : (new DetectCoreCount())();
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
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $this->processor,
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
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: new AnalysisResolver($patterns),
            reporter: $this->reporter,
            processor: $this->processor,
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
            pathResolver: $resolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $this->processor,
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
            pathResolver: $this->pathResolver,
            fileResolver: $resolver,
            analysisResolver: $this->analysisResolver,
            reporter: $this->reporter,
            processor: $this->processor,
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
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $resolver,
            reporter: $this->reporter,
            processor: $this->processor,
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
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: $reporter,
            processor: $this->processor,
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
            pathResolver: $this->pathResolver,
            fileResolver: $this->fileResolver,
            analysisResolver: $this->analysisResolver,
            reporter: new AgentReporter(),
            processor: $this->processor,
        );
    }
}
