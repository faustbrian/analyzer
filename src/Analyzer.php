<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer;

use Cline\Analyzer\Config\AnalyzerConfig;
use Cline\Analyzer\Contracts\FileResolverInterface;
use Cline\Analyzer\Contracts\PathResolverInterface;
use Cline\Analyzer\Contracts\ProcessorInterface;
use Cline\Analyzer\Contracts\ReporterInterface;
use Cline\Analyzer\Data\AnalysisResult;
use Closure;
use RuntimeException;
use SplFileInfo;
use Throwable;

use function array_any;
use function throw_if;

/**
 * Orchestrates PHP file analysis for class reference validation.
 *
 * Main entry point for analyzing PHP files to detect missing or invalid class references.
 * Resolves file paths, processes files either serially or in parallel, validates class
 * existence, and reports results. Designed as an immutable orchestrator that coordinates
 * path resolution, file discovery, analysis execution, and progress reporting.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class Analyzer
{
    /**
     * Create a new analyzer instance.
     *
     * @param AnalyzerConfig $config configuration specifying paths to analyze, parallelization
     *                               settings, ignore patterns, and resolver implementations for
     *                               customizing path resolution, file filtering, class analysis,
     *                               and result reporting behavior
     */
    public function __construct(
        private AnalyzerConfig $config,
    ) {}

    /**
     * Analyze PHP files to validate class references.
     *
     * Resolves configured paths, discovers PHP files, and analyzes each file to identify
     * referenced classes that don't exist. Executes analysis either in parallel using a
     * process pool or serially based on configuration. Reports progress and results through
     * the configured reporter implementation.
     *
     * @throws RuntimeException When required resolvers or reporter are not properly configured
     *
     * @return array<AnalysisResult> Collection of analysis results, one per file analyzed
     */
    public function analyze(): array
    {
        $pathResolver = $this->config->pathResolver;
        $fileResolver = $this->config->fileResolver;
        $reporter = $this->config->reporter;

        throw_if(!$pathResolver instanceof PathResolverInterface || !$fileResolver instanceof FileResolverInterface || !$reporter instanceof ReporterInterface, RuntimeException::class, 'Required resolvers and reporter must be configured');

        /** @var array<string> $paths */
        $paths = $pathResolver->resolve($this->config->paths);

        /** @var array<SplFileInfo> $files */
        $files = $fileResolver->getFiles($paths);

        $reporter->start($files);

        /** @var Closure(SplFileInfo): AnalysisResult $callback */
        $callback = function (SplFileInfo $file) use ($reporter): AnalysisResult {
            try {
                $result = $this->config->analysisResolver->analyze($file);
            } catch (Throwable $throwable) {
                $result = AnalysisResult::error($file, $throwable->getMessage());
            }

            $reporter->progress($result);

            return $result;
        };

        /** @var ProcessorInterface $processor */
        $processor = $this->config->processor;
        $results = $processor->process($files, $callback);

        $reporter->finish($results);

        return $results;
    }

    /**
     * Check if any analysis results contain failures.
     *
     * Scans the results collection to determine if any file analysis detected missing
     * or invalid class references. Useful for determining exit codes in CLI applications
     * or triggering failure workflows in CI/CD pipelines.
     *
     * @param  array<AnalysisResult> $results Collection of analysis results to check
     * @return bool                  True if at least one result indicates failure, false if all succeeded
     */
    public function hasFailures(array $results): bool
    {
        return array_any($results, fn (AnalysisResult $result): bool => !$result->success);
    }
}
