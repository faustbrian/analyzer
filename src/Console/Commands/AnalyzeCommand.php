<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Console\Commands;

use Cline\Analyzer\Actions\DetectCoreCount;
use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;
use Cline\Analyzer\Contracts\AnalysisResolverInterface;
use Cline\Analyzer\Contracts\FileResolverInterface;
use Cline\Analyzer\Contracts\PathResolverInterface;
use Cline\Analyzer\Contracts\ProcessorInterface;
use Cline\Analyzer\Contracts\ReporterInterface;
use Cline\Analyzer\Enums\Verbosity;
use Cline\Analyzer\Processors\ParallelProcessor;
use Cline\Analyzer\Processors\SerialProcessor;
use Cline\Analyzer\Reporters\AgentReporter;
use Cline\Analyzer\Resolvers\RouteAnalysisResolver;
use Cline\Analyzer\Resolvers\TranslationAnalysisResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use function app;
use function assert;
use function base_path;
use function file_exists;
use function Laravel\Prompts\confirm;
use function preg_match;
use function str_starts_with;

/**
 * Laravel Artisan command for analyzing PHP class references.
 *
 * Provides a convenient CLI interface for running the analyzer with configurable
 * options for paths, parallelization, ignore patterns, and output modes. Supports
 * both human-readable output and AI agent orchestration mode for automated fixes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AnalyzeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyzer:analyze
                            {paths?* : Paths to analyze (files or directories)}
                            {--parallel : Force parallel processing (overrides config)}
                            {--serial : Force serial processing (overrides config)}
                            {--workers=auto : Number of parallel workers (auto or integer)}
                            {--ignore=* : Glob patterns for class names to ignore}
                            {--exclude=* : Glob patterns for files/directories to exclude}
                            {--lang : Analyze translation key references}
                            {--route : Analyze route name references}
                            {--agent : Output AI agent orchestration prompts}
                            {--ci : Skip confirmation prompts for missing paths (CI mode)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze PHP files for missing class references, translation keys, or routes';

    /**
     * Execute the console command to analyze PHP files.
     *
     * Orchestrates the complete analysis workflow: validates paths exist, loads configuration
     * from both config file and CLI options (with CLI options taking precedence), instantiates
     * all resolver and processor components with dependency injection, and executes the analyzer.
     * The analyzer can check for missing class references (default), translation keys (--lang),
     * or route names (--route). In CI mode, skips interactive confirmation prompts for missing
     * paths. Returns appropriate exit code for CI/CD pipeline integration.
     *
     * @return int Command exit code: self::SUCCESS (0) when no issues found, self::FAILURE (1) when issues detected
     */
    public function handle(): int
    {
        /** @var array<string> $defaultPaths */
        $defaultPaths = Config::get('analyzer.paths', ['app', 'tests']);

        /** @var array<string> $paths */
        $paths = $this->argument('paths') ?: $defaultPaths;

        // Validate paths exist
        $missingPaths = [];
        $validPaths = [];

        foreach ($paths as $path) {
            $absolutePath = $this->isAbsolutePath($path) ? $path : base_path($path);

            if (file_exists($absolutePath)) {
                $validPaths[] = $path;
            } else {
                $missingPaths[] = $path;
            }
        }

        if ($missingPaths !== []) {
            $this->warn('The following paths do not exist:');

            foreach ($missingPaths as $missing) {
                $this->line('  - '.$missing);
            }

            if ($validPaths === []) {
                $this->error('No valid paths to analyze.');

                return self::FAILURE;
            }

            if (!$this->option('ci') && !app()->runningUnitTests() && !confirm('Do you want to continue with the remaining paths?', false)) {
                return self::FAILURE; // @codeCoverageIgnore
            }
        }

        $paths = $validPaths;

        /** @var int|string $defaultWorkers */
        $defaultWorkers = Config::get('analyzer.workers', 'auto');
        $workersOption = $this->option('workers') ?? $defaultWorkers;
        $workers = $workersOption === 'auto' ? DetectCoreCount::handle() : (int) $workersOption;

        /** @var array<string> $defaultIgnore */
        $defaultIgnore = Config::get('analyzer.ignore', []);

        /** @var array<string> $ignore */
        $ignore = $this->option('ignore') ?: $defaultIgnore;

        /** @var array<string> $defaultExclude */
        $defaultExclude = Config::get('analyzer.exclude', []);

        /** @var array<string> $exclude */
        $exclude = $this->option('exclude') ?: $defaultExclude;

        $agentMode = $this->option('agent');

        // Instantiate resolvers from config
        /** @var class-string $pathResolverClass */
        $pathResolverClass = Config::get('analyzer.path_resolver');
        $pathResolver = app($pathResolverClass);
        assert($pathResolver instanceof PathResolverInterface);

        /** @var class-string $fileResolverClass */
        $fileResolverClass = Config::get('analyzer.file_resolver');
        $fileResolver = app($fileResolverClass);
        assert($fileResolver instanceof FileResolverInterface);

        // Determine which analysis resolver to use based on flags
        if ($this->option('lang')) {
            /** @var array<string, mixed> $translationConfig */
            $translationConfig = Config::get('analyzer.translation', []);

            /** @var class-string $analysisResolverClass */
            $analysisResolverClass = $translationConfig['resolver'] ?? TranslationAnalysisResolver::class;

            $analysisResolver = new $analysisResolverClass(
                langPath: $translationConfig['lang_path'] ?? base_path('lang'),
                locales: $translationConfig['locales'] ?? ['en'],
                reportDynamic: $translationConfig['report_dynamic'] ?? true,
                vendorPath: $translationConfig['vendor_path'] ?? null,
                ignore: $translationConfig['ignore'] ?? [],
                includePatterns: $translationConfig['include_patterns'] ?? null,
            );
        } elseif ($this->option('route')) {
            /** @var array<string, mixed> $routeConfig */
            $routeConfig = Config::get('analyzer.route', []);

            /** @var class-string $analysisResolverClass */
            $analysisResolverClass = $routeConfig['resolver'] ?? RouteAnalysisResolver::class;

            $analysisResolver = new $analysisResolverClass(
                routesPath: $routeConfig['routes_path'] ?? base_path('routes'),
                cacheRoutes: $routeConfig['cache_routes'] ?? true,
                cacheTtl: $routeConfig['cache_ttl'] ?? 3_600,
                reportDynamic: $routeConfig['report_dynamic'] ?? true,
                includePatterns: $routeConfig['include_patterns'] ?? null,
                ignorePatterns: $routeConfig['ignore_patterns'] ?? null,
                app: app(),
            );
        } else {
            /** @var class-string $analysisResolverClass */
            $analysisResolverClass = Config::get('analyzer.analysis_resolver');
            $analysisResolver = app($analysisResolverClass);
        }

        assert($analysisResolver instanceof AnalysisResolverInterface);

        /** @var class-string $reporterClass */
        $reporterClass = Config::get('analyzer.reporter');
        $reporter = $agentMode ? app(AgentReporter::class) : app($reporterClass);
        assert($reporter instanceof ReporterInterface);

        // Determine processor based on flags or config
        $processor = null;

        if ($this->option('parallel')) {
            $processor = new ParallelProcessor($workers);
        } elseif ($this->option('serial')) {
            $processor = new SerialProcessor();
        } else {
            /** @var class-string $processorClass */
            $processorClass = Config::get('analyzer.processor');
            $processor = app($processorClass);
            assert($processor instanceof ProcessorInterface);

            // Update workers if ParallelProcessor from config
            if ($processor instanceof ParallelProcessor && $this->option('workers')) {
                $processor = new ParallelProcessor($workers);
            }
        }

        // Map Laravel verbosity levels to analyzer verbosity
        // VERBOSITY_QUIET = 16, VERBOSITY_NORMAL = 32, VERBOSITY_VERBOSE = 64,
        // VERBOSITY_VERY_VERBOSE = 128, VERBOSITY_DEBUG = 256
        $verbosity = match ($this->getOutput()->getVerbosity()) {
            256 => Verbosity::Debug,        // -vvv / debug
            128 => Verbosity::VeryVerbose,  // -vv / very verbose
            64 => Verbosity::Verbose,       // -v / verbose
            default => Verbosity::Normal,   // normal or quiet
        };

        $config = AnalyzerConfig::make()
            ->paths($paths)
            ->workers($workers)
            ->ignore($ignore)
            ->exclude($exclude)
            ->pathResolver($pathResolver)
            ->fileResolver($fileResolver)
            ->analysisResolver($analysisResolver)
            ->reporter($reporter)
            ->processor($processor)
            ->verbosity($verbosity);

        $analyzer = new Analyzer($config);
        $results = $analyzer->analyze();

        return $analyzer->hasFailures($results) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Check if a path is absolute.
     *
     * Determines whether a file system path is absolute or relative by checking for
     * Unix-style absolute paths (starting with '/') or Windows-style absolute paths
     * (matching 'C:\' or 'C:/' pattern). Used during path validation to determine
     * if base_path() resolution is needed.
     *
     * @param  string $path The file system path to evaluate
     * @return bool   True if the path is absolute (Unix or Windows style), false if relative
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }
}
