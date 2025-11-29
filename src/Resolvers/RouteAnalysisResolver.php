<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Resolvers;

use Cline\Analyzer\Analysis\BladeParser;
use Cline\Analyzer\Analysis\RouteCallVisitor;
use Cline\Analyzer\Contracts\AnalysisResolverInterface;
use Cline\Analyzer\Data\AnalysisResult;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SplFileInfo;
use Throwable;

use function array_any;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function class_exists;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function is_array;
use function is_dir;
use function md5;
use function mkdir;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function serialize;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function unlink;
use function unserialize;

/**
 * Analyzes files for route name references and validates their existence.
 *
 * Validates route() calls against Laravel's registered routes. Loads routes by
 * bootstrapping Laravel application, caches route list for performance, handles
 * Blade templates, and reports missing or invalid route names.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RouteAnalysisResolver implements AnalysisResolverInterface
{
    /**
     * PHP parser instance for parsing PHP and Blade files.
     */
    private Parser $parser;

    /**
     * Loaded route names mapped to their existence.
     *
     * Keys are route names (e.g., "users.index"), values are true for all existing routes.
     *
     * @var array<string, bool>
     */
    private array $routeNames;

    /**
     * Creates a new route analysis resolver.
     *
     * @param string             $routesPath      Path to Laravel routes directory containing route definition
     *                                            files. Supports both root-level route files and routes/
     *                                            subdirectory structure for scanning route definitions.
     * @param bool               $cacheRoutes     Enable route caching for improved performance. When enabled,
     *                                            loaded routes are cached to the system temp directory and
     *                                            reused until cache expires or route files are modified.
     * @param int                $cacheTtl        Cache time-to-live in seconds. Defaults to 3600 seconds
     *                                            (1 hour). After this period, the cache is invalidated and
     *                                            routes are reloaded from source files.
     * @param bool               $reportDynamic   Report dynamic route names as warnings in the analysis results.
     *                                            When true, route() calls with variables or expressions trigger
     *                                            warnings since they cannot be statically validated.
     * @param null|array<string> $includePatterns Only validate routes matching these glob-style patterns.
     *                                            When specified, routes not matching any pattern are ignored
     *                                            during validation. Supports wildcards (* for any characters).
     *                                            Example: ['admin.*', 'api.v1.*'] to only validate those namespaces.
     * @param null|array<string> $ignorePatterns  Ignore routes matching these glob-style patterns during
     *                                            validation. Routes matching any ignore pattern are skipped
     *                                            even if they don't exist. Useful for excluding dynamic routes
     *                                            or routes generated at runtime. Supports wildcards.
     * @param null|Application   $app             Laravel application instance for bootstrapping and loading
     *                                            routes directly from the router. When provided, routes are
     *                                            loaded from the live application instead of static file parsing.
     * @param null|Parser        $parser          Optional PHP parser instance for testing or custom parser
     *                                            configuration. When null, creates a parser for the newest
     *                                            supported PHP version automatically.
     * @param null|BladeParser   $bladeParser     Optional Blade template parser for converting Blade syntax
     *                                            to PHP before analysis. When null, Blade files are skipped.
     *                                            Defaults to a new BladeParser instance.
     */
    public function __construct(
        private string $routesPath,
        private bool $cacheRoutes = true,
        private int $cacheTtl = 3_600,
        private bool $reportDynamic = true,
        private ?array $includePatterns = null,
        private ?array $ignorePatterns = null,
        private ?Application $app = null,
        ?Parser $parser = null,
        private ?BladeParser $bladeParser = new BladeParser(),
    ) {
        $this->parser = $parser ?? new ParserFactory()->createForNewestSupportedVersion();
        $this->routeNames = $this->loadRoutes();
    }

    /**
     * Analyzes a file for route name references and validates their existence.
     *
     * Parses PHP and Blade files to discover all route() and Route::has() calls,
     * validates route names against loaded routes, and reports missing routes.
     * Dynamic routes (using variables or expressions) are flagged as warnings
     * when reportDynamic is enabled.
     *
     * @param  SplFileInfo    $file File to analyze (PHP or Blade template)
     * @return AnalysisResult Analysis result containing discovered route references,
     *                        any missing route names, and warnings for dynamic routes
     */
    public function analyze(SplFileInfo $file): AnalysisResult
    {
        $path = $file->getRealPath();
        $content = (string) file_get_contents($path);

        // Parse Blade templates to PHP first
        if (BladeParser::isBladeFile($path) && $this->bladeParser instanceof BladeParser) {
            $content = $this->bladeParser->parse($content);
        }

        try {
            $ast = $this->parser->parse($content);
        } catch (Error $error) {
            return AnalysisResult::error($file, $error->getMessage());
        }

        if ($ast === null) {
            return AnalysisResult::error($file, 'Failed to parse file');
        }

        $traverser = new NodeTraverser();
        $visitor = new RouteCallVisitor();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $routes = $visitor->getRouteCalls() ?? [];

        // Find missing routes and warnings (filter first to build correct references)
        $missing = [];
        $warnings = [];
        $validatedRoutes = [];

        foreach ($routes as $route) {
            if ($route['dynamic']) {
                if ($this->reportDynamic) {
                    $warnings[] = [
                        'type' => 'dynamic_route',
                        'line' => $route['line'],
                        'reason' => $route['reason'] ?? 'dynamic',
                        'method' => $route['type'],
                    ];
                }

                continue;
            }

            $name = $route['name'];

            // Skip ignored/filtered routes
            if ($name !== null && $this->shouldIgnoreRoute($name)) {
                continue;
            }

            // Add to validated routes for references
            $validatedRoutes[] = $route;

            // Check for empty route names
            if ($name === '' || $route['empty']) {
                $missing[] = $name;
                $warnings[] = [
                    'type' => 'empty_route',
                    'message' => 'Route name is empty',
                    'line' => $route['line'],
                ];

                continue;
            }

            if ($name !== null && !$this->routeExists($name)) {
                $missing[] = $name;
            }
        }

        // Build references from validated routes
        $references = array_map(
            fn (array $r): string => $r['name'] ?? '',
            $validatedRoutes,
        );

        // Filter out null values from missing array
        $missing = array_map(fn (?string $m): string => (string) $m, $missing);

        if ($missing === []) {
            return new AnalysisResult($file, $references, [], true, null, $warnings);
        }

        return new AnalysisResult($file, $references, $missing, false, null, $warnings);
    }

    /**
     * Checks if a route name exists in the loaded routes.
     *
     * @param  string $name Route name to check (e.g., "users.index")
     * @return bool   True if the route exists in the application's registered routes
     */
    public function routeExists(string $name): bool
    {
        return array_key_exists($name, $this->routeNames);
    }

    /**
     * Gets all loaded route names.
     *
     * @return array<string> List of all route names loaded from the application
     */
    public function getLoadedRoutes(): array
    {
        return array_keys($this->routeNames);
    }

    /**
     * Checks if a class exists (required by interface but not used for routes).
     *
     * @param  string $class Class name to check
     * @return bool   Always returns true as class checking is not applicable to route analysis
     */
    public function classExists(string $class): bool
    {
        return true;
    }

    /**
     * Checks if a route should be ignored based on configured patterns.
     *
     * Applies both include and ignore pattern filtering. When include patterns
     * are specified, routes must match at least one include pattern. Routes
     * matching any ignore pattern are always excluded.
     *
     * @param  string $name Route name to check against patterns
     * @return bool   True if route should be ignored (excluded from validation)
     */
    private function shouldIgnoreRoute(string $name): bool
    {
        // If include patterns specified, only include matching routes
        if ($this->includePatterns !== null) {
            $included = array_any($this->includePatterns, fn (string $pattern): bool => $this->matchesPattern($name, $pattern));

            if (!$included) {
                return true;
            }
        }

        // Check ignore patterns
        if ($this->ignorePatterns !== null) {
            foreach ($this->ignorePatterns as $pattern) {
                if ($this->matchesPattern($name, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if a route name matches a glob-style pattern.
     *
     * Converts glob wildcard patterns to regular expressions for matching.
     * Supports * for any characters and treats dots literally for matching
     * Laravel's dot-notation route names.
     *
     * @param  string $name    Route name to match against (e.g., "admin.users.index")
     * @param  string $pattern Glob-style pattern supporting * wildcard (e.g., "admin.*")
     * @return bool   True if route name matches the pattern
     */
    private function matchesPattern(string $name, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\\*', '\\.'], ['.*', '\\.'], preg_quote($pattern, '/')).'$/';

        return (bool) preg_match($regex, $name);
    }

    /**
     * Loads all route names from Laravel application with caching support.
     *
     * Attempts to load routes from cache first if caching is enabled. If cache
     * is invalid or missing, bootstraps Laravel to load fresh routes and updates
     * the cache for subsequent runs.
     *
     * @return array<string, bool> Map of route names to their existence (all values true)
     */
    private function loadRoutes(): array
    {
        if ($this->cacheRoutes) {
            $cached = $this->loadFromCache();

            if ($cached !== null) {
                return $cached;
            }
        }

        $routes = $this->bootstrapAndLoadRoutes();

        if ($this->cacheRoutes) {
            $this->saveToCache($routes);
        }

        return $routes;
    }

    /**
     * Bootstraps Laravel and loads route names from the router.
     *
     * Attempts multiple strategies in order of preference:
     * 1. Direct application instance route collection access
     * 2. Route facade static access (for Laravel applications)
     * 3. Static file parsing as fallback when Laravel is unavailable
     *
     * @return array<string, bool> Map of discovered route names to their existence
     */
    private function bootstrapAndLoadRoutes(): array
    {
        // Try Laravel bootstrap if app is available
        if ($this->app instanceof Application) {
            try {
                $routeCollection = $this->app->make(Router::class)->getRoutes();
                $namedRoutes = [];

                /** @var RouteInstance $route */
                foreach ($routeCollection->getRoutes() as $route) {
                    $name = $route->getName();

                    if ($name !== null) {
                        $namedRoutes[$name] = true;
                    }
                }

                return $namedRoutes;
            } catch (Throwable) {
                // Fall through to static parsing
            }
        }

        // Try using Route facade if available
        if (class_exists(Route::class)) {
            try {
                $routeCollection = Route::getRoutes();
                $namedRoutes = [];

                /** @var RouteInstance $route */
                foreach ($routeCollection->getRoutes() as $route) {
                    $name = $route->getName();

                    if ($name !== null) {
                        $namedRoutes[$name] = true;
                    }
                }

                return $namedRoutes;
                // @codeCoverageIgnoreStart
            } catch (Throwable) {
                // This catch block only executes when Route facade throws an exception.
                // In Laravel test environment, Route facade functions correctly.
                // Fall through to static parsing below
                // @codeCoverageIgnoreEnd
            }
        }

        // Fall back to static parsing of route files
        // @codeCoverageIgnoreStart
        // This line is only reached when both app is not an Application instance
        // AND Route facade doesn't exist or throws. In Laravel test environment,
        // Route facade always exists and works, making this unreachable.
        return $this->parseRouteFiles();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Parses route files statically to extract route names using regex.
     *
     * Scans both root-level and routes/ subdirectory for PHP files containing
     * route definitions. Extracts route names from ->name() and ->names() method
     * calls without executing the PHP code.
     *
     * @return array<string, bool> Map of route names discovered via static analysis
     */
    private function parseRouteFiles(): array
    {
        $namedRoutes = [];

        // Look for route files in both root and routes/ subdirectory
        $patterns = [
            $this->routesPath.'/*.php',
            $this->routesPath.'/routes/*.php',
        ];

        foreach ($patterns as $pattern) {
            $files = glob($pattern) ?: [];

            foreach ($files as $file) {
                $routes = $this->extractRoutesFromFile($file);
                $namedRoutes = array_merge($namedRoutes, $routes);
            }
        }

        return $namedRoutes;
    }

    /**
     * Extracts route names from a single route file using regex patterns.
     *
     * Matches two Laravel route naming patterns:
     * 1. Single route names: ->name('route.name')
     * 2. Resource route names: ->names(['index' => 'route.index', ...])
     *
     * @param  string              $file Absolute path to route file to parse
     * @return array<string, bool> Route names found in the file
     */
    private function extractRoutesFromFile(string $file): array
    {
        $content = file_get_contents($file);
        $namedRoutes = [];

        // Match ->name('route.name') patterns
        if (preg_match_all('/->name\([\'"]([^\'"]+)[\'"]\)/', $content ?: '', $matches)) {
            foreach ($matches[1] as $name) {
                $namedRoutes[$name] = true;
            }
        }

        // Match ->names(['key' => 'route.name']) patterns for resource routes
        if (preg_match_all('/->names\(\[(.*?)\]\)/s', $content ?: '', $matches)) {
            foreach ($matches[1] as $namesArray) {
                if (preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $namesArray, $nameMatches)) {
                    foreach ($nameMatches[2] as $name) {
                        $namedRoutes[$name] = true;
                    }
                }
            }
        }

        return $namedRoutes;
    }

    /**
     * Loads routes from cache if available and valid.
     *
     * Validates cache by checking:
     * 1. Cache file exists
     * 2. Cache has not exceeded TTL
     * 3. Route files have not been modified since cache was created
     *
     * @return null|array<string, bool> Cached routes or null if cache is invalid or expired
     */
    private function loadFromCache(): ?array
    {
        $cacheFile = $this->getCacheFilePath();

        if (!file_exists($cacheFile)) {
            return null;
        }

        // Check if cache is expired
        if ((Date::now()->getTimestamp() - filemtime($cacheFile)) > $this->cacheTtl) {
            unlink($cacheFile);

            return null;
        }

        // Check if routes files have been modified
        if ($this->routeFilesModified($cacheFile)) {
            // @codeCoverageIgnoreStart
            // This unlink only executes when route files are modified after cache creation.
            // While tested via "it deletes cache file when route files are modified",
            // coverage tools may not detect this path due to timing sensitivity.
            unlink($cacheFile);

            return null;
            // @codeCoverageIgnoreEnd
        }

        $contents = file_get_contents($cacheFile);

        /** @var mixed $unserialized */
        $unserialized = unserialize($contents ?: '');

        if (!is_array($unserialized)) {
            return null;
        }

        /** @var array<string, bool> $unserialized */
        return $unserialized;
    }

    /**
     * Saves routes to cache file in system temp directory.
     *
     * Creates cache directory if it doesn't exist and serializes the routes
     * array for fast deserialization on subsequent analyzer runs.
     *
     * @param array<string, bool> $routes Route names to cache
     */
    private function saveToCache(array $routes): void
    {
        $cacheFile = $this->getCacheFilePath();
        $cacheDir = dirname($cacheFile);

        // @codeCoverageIgnoreStart
        // $cacheDir is dirname(sys_get_temp_dir() . '/file'), which equals sys_get_temp_dir().
        // The system temp directory always exists, so is_dir() is always true and mkdir never runs.
        // This defensive code protects against edge cases where temp directory is missing.
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0o755, true);
        }

        // @codeCoverageIgnoreEnd

        file_put_contents($cacheFile, serialize($routes));
    }

    /**
     * Gets cache file path unique to this routes directory.
     *
     * Generates a unique cache filename based on MD5 hash of the routes path,
     * ensuring different projects don't share cache files.
     *
     * @return string Absolute path to cache file in system temp directory
     */
    private function getCacheFilePath(): string
    {
        $cacheKey = md5($this->routesPath);

        return sys_get_temp_dir().sprintf('/analyzer-routes-%s.cache', $cacheKey);
    }

    /**
     * Checks if route files have been modified since cache was created.
     *
     * Compares modification time of all route files against the cache file's
     * modification time. Returns true if any route file is newer, indicating
     * the cache should be invalidated and routes reloaded.
     *
     * @param  string $cacheFile Path to cache file to compare against
     * @return bool   True if any route files are newer than cache (cache is stale)
     */
    private function routeFilesModified(string $cacheFile): bool
    {
        $cacheTime = filemtime($cacheFile);
        $routeFiles = glob($this->routesPath.'/*.php') ?: [];

        return array_any($routeFiles, fn ($file): bool => filemtime($file) > $cacheTime);
    }
}
