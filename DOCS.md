## Table of Contents

1. [Overview](#doc-docs-readme) (`docs/README.md`)
2. [Configuration](#doc-docs-configuration) (`docs/configuration.md`)
3. [Custom Resolvers](#doc-docs-custom-resolvers) (`docs/custom-resolvers.md`)
4. [Examples](#doc-docs-examples) (`docs/examples.md`)
5. [Parallel Processing](#doc-docs-parallel-processing) (`docs/parallel-processing.md`)
<a id="doc-docs-readme"></a>

## Installation

Install via Composer:

```bash
composer require cline/analyzer
```

The service provider will be automatically registered via Laravel's package discovery.

## What is Analyzer?

Analyzer is a configurable parallel PHP code analyzer that validates references in your codebase. It supports:

- **Class References** - Validate that all referenced classes exist
- **Translation Keys** - Validate `trans()`, `__()`, and `Lang::get()` calls against language files
- **Route Names** - Validate `route()` and `Route::has()` calls against registered routes
- **Parallel Processing** - Analyze files concurrently with auto-detected CPU cores
- **AI Agent Mode** - Generate XML prompts for automated fixing with parallel AI agents
- **Laravel Prompts UI** - Beautiful terminal reporting with progress and statistics

## Quick Start

### Artisan Command (Recommended)

```bash
# Analyze default paths (app, tests)
php artisan analyzer:analyze

# Analyze specific paths
php artisan analyzer:analyze app/Models app/Services

# Enable parallel processing with auto-detected cores
php artisan analyzer:analyze --workers=auto

# Analyze translation keys
php artisan analyzer:analyze --lang

# Analyze route names
php artisan analyzer:analyze --route

# AI agent mode for automated fixing
php artisan analyzer:analyze --agent
```

### Programmatic Usage

```php
<?php

use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;

$config = AnalyzerConfig::make()
    ->paths(['app', 'tests'])
    ->workers(0)  // 0 = auto-detect CPU cores
    ->ignore(['Illuminate\\*'])
    ->exclude(['vendor', 'storage']);

$analyzer = new Analyzer($config);
$results = $analyzer->analyze();

exit($analyzer->hasFailures($results) ? 1 : 0);
```

## Configuration File

Publish the configuration:

```bash
php artisan vendor:publish --tag=analyzer-config
```

This creates `analyzer.php` in your project root:

```php
<?php

use Cline\Analyzer\Config\AnalyzerConfig;

return AnalyzerConfig::make()
    ->paths(['app', 'tests'])
    ->workers(0)  // 0 = auto-detect CPU cores
    ->ignore(['Illuminate\\*', 'Symfony\\*'])
    ->exclude(['vendor', 'node_modules', 'storage']);
```

## Output Modes

### Human-Readable Output (Default)

The analyzer uses Laravel Prompts for beautiful terminal output with:

- Progress indicators during analysis
- Summary statistics (total files, missing references, top broken namespaces)
- Detailed failure reports with tables
- Color-coded messages for easy scanning

### AI Agent Mode

Use `--agent` flag for XML-structured orchestration prompts:

```bash
php artisan analyzer:analyze --agent
```

Outputs XML with:

- Analysis summary
- Parallel agent assignments grouped by namespace
- Specific fix instructions for each agent
- Sequential fallback strategy

Perfect for spawning multiple AI agents to fix issues in parallel.

## Exit Codes

- `0`: All references are valid
- `1`: Missing references found

## Next Steps

- **[Configuration](configuration)** - Learn all configuration options
- **[Parallel Processing](parallel-processing)** - Configure worker count and memory usage
- **[Custom Resolvers](custom-resolvers)** - Implement custom resolution logic
- **[Examples](examples)** - CI/CD integration, pre-commit hooks, and more

<a id="doc-docs-configuration"></a>

## Configuration File

Create an `analyzer.php` configuration file:

```php
<?php

use Cline\Analyzer\Config\AnalyzerConfig;

return AnalyzerConfig::make()
    ->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->workers(0)  // 0 = auto-detect CPU cores
    ->ignore([
        'Illuminate\\*',
        'Symfony\\*',
        'PHPUnit\\*',
    ])
    ->exclude([
        'vendor',
        'node_modules',
        'storage',
    ]);
```

## Configuration Options

### Paths

Specify directories or individual files to analyze:

```php
$config->paths(['src', 'tests', 'app/Models/User.php']);
```

### Worker Count

Configure parallel processing worker count:

```php
// Auto-detect CPU cores (default behavior when workers = 0)
$config->workers(0);

// Specify exact worker count
$config->workers(4);   // Use 4 workers
$config->workers(8);   // Use 8 workers
```

Command-line override:

```bash
php artisan analyzer:analyze --workers=auto    # Auto-detect
php artisan analyzer:analyze --workers=8       # Use 8 workers
```

The analyzer automatically detects your CPU core count when `workers` is set to `0` or `'auto'`. This uses:

- **macOS**: `sysctl -n hw.ncpu`
- **Linux**: `nproc`
- **Fallback**: 4 cores if detection fails

### Ignore Patterns

Skip class references matching patterns:

```php
$config->ignore([
    'Illuminate\\*',           // All Illuminate classes
    'Symfony\\Component\\*',   // Symfony components
    'Test\\*',                 // Test namespace
]);
```

Patterns use `fnmatch()` syntax:

- `*` matches any characters
- `?` matches a single character
- `[abc]` matches a, b, or c

### Exclude Patterns

Exclude files and directories from being scanned:

```php
$config->exclude([
    'vendor',              // Skip vendor directory
    'node_modules',        // Skip node_modules
    'storage',             // Skip storage directory
    'bootstrap/cache',     // Skip Laravel cache
    'build',               // Skip build artifacts
    '*.blade.php',         // Skip Blade templates
    'tests/fixtures/*',    // Skip test fixtures
]);
```

**Difference between `ignore()` and `exclude()`:**

- `exclude()` - Prevents files/directories from being scanned at all (performance optimization)
- `ignore()` - Scans files but skips specific class name patterns during analysis

Exclude patterns support:

- Simple substring matching: `'vendor'` matches any path containing "vendor"
- Glob patterns: `'tests/fixtures/*'` matches any file in tests/fixtures/
- File patterns: `'*.blade.php'` matches all Blade template files

Command-line override:

```bash
php artisan analyzer:analyze --exclude=vendor --exclude=storage
```

## Chaining Methods

All configuration methods return a new instance, allowing fluent chaining:

```php
$config = AnalyzerConfig::make()
    ->paths(['src'])
    ->workers(2)
    ->ignore(['Test\\*'])
    ->exclude(['vendor', 'storage'])
    ->pathResolver(new CustomPathResolver());
```

## Loading Configuration

Load from a file:

```php
$config = require __DIR__.'/analyzer.php';
$analyzer = new Analyzer($config);
```

## Translation Analysis Configuration

Configure translation key validation:

```php
$config->analysisResolver(new TranslationAnalysisResolver(
    langPath: base_path('lang'),
    locales: ['en', 'es', 'fr'],
    reportDynamic: true,
    vendorPath: base_path('vendor/*/lang'),
    ignore: ['debug.*', 'temp.*'],
    includePatterns: ['validation.*', 'auth.*']
));
```

| Option | Description |
|--------|-------------|
| `langPath` | Path to lang directory |
| `locales` | Array of locales to validate |
| `reportDynamic` | Report dynamic keys as warnings |
| `vendorPath` | Path to vendor translations |
| `ignore` | Patterns to ignore |
| `includePatterns` | Only validate these patterns |

## Route Analysis Configuration

Configure route name validation:

```php
$config->analysisResolver(new RouteAnalysisResolver(
    routesPath: base_path('routes'),
    cacheRoutes: true,
    cacheTtl: 3600,
    reportDynamic: true,
    includePatterns: ['admin.*', 'api.*'],
    ignorePatterns: ['debug.*'],
    app: app()
));
```

| Option | Description |
|--------|-------------|
| `routesPath` | Path to routes directory |
| `cacheRoutes` | Enable route caching |
| `cacheTtl` | Cache TTL in seconds |
| `reportDynamic` | Report dynamic route names as warnings |
| `includePatterns` | Only validate these patterns |
| `ignorePatterns` | Patterns to ignore |
| `app` | Laravel application instance |

<a id="doc-docs-custom-resolvers"></a>

The analyzer provides four resolver interfaces for customization.

## PathResolver

Controls which paths are analyzed:

```php
use Cline\Analyzer\Contracts\PathResolverInterface;

class CustomPathResolver implements PathResolverInterface
{
    public function resolve(array $paths): array
    {
        // Filter paths, expand globs, resolve symlinks, etc.
        return array_map(fn($p) => realpath($p), $paths);
    }
}

$config->pathResolver(new CustomPathResolver());
```

## FileResolver

Determines which files to analyze and filters files:

```php
use Cline\Analyzer\Contracts\FileResolverInterface;
use SplFileInfo;

class CustomFileResolver implements FileResolverInterface
{
    public function shouldAnalyze(SplFileInfo $file): bool
    {
        // Only analyze files in src/ directory
        return str_contains($file->getPath(), '/src/');
    }

    public function getFiles(array $paths): array
    {
        // Custom file discovery logic
        $files = [];
        foreach ($paths as $path) {
            // ... your logic
        }
        return $files;
    }
}

$config->fileResolver(new CustomFileResolver());
```

## AnalysisResolver

Controls how files are analyzed and which classes are considered missing:

```php
use Cline\Analyzer\Contracts\AnalysisResolverInterface;
use Cline\Analyzer\Data\AnalysisResult;
use SplFileInfo;

class CustomAnalysisResolver implements AnalysisResolverInterface
{
    public function analyze(SplFileInfo $file): AnalysisResult
    {
        // Custom analysis logic
        $references = $this->extractReferences($file);
        $missing = $this->findMissing($references);

        return count($missing) > 0
            ? AnalysisResult::failure($file, $references, $missing)
            : AnalysisResult::success($file, $references);
    }

    public function classExists(string $class): bool
    {
        // Custom class existence check
        return class_exists($class) || $this->isInVendor($class);
    }
}

$config->analysisResolver(new CustomAnalysisResolver());
```

## Built-in Analysis Resolvers

### RouteAnalysisResolver

Validates route names against Laravel's registered routes:

```php
use Cline\Analyzer\Resolvers\RouteAnalysisResolver;

$routeResolver = new RouteAnalysisResolver(
    routesPath: base_path('routes'),
    cacheRoutes: true,
    cacheTtl: 3600,
    reportDynamic: true,
    includePatterns: ['admin.*', 'api.*'],
    ignorePatterns: ['debug.*'],
    app: app()
);

$config->analysisResolver($routeResolver);
```

Features:

- Validates `route()` and `Route::has()` calls
- Supports Laravel application bootstrapping or static file parsing
- Caches routes for performance
- Reports dynamic route names as warnings
- Pattern-based filtering with wildcards

### TranslationAnalysisResolver

Validates translation keys against Laravel's translation files:

```php
use Cline\Analyzer\Resolvers\TranslationAnalysisResolver;

$translationResolver = new TranslationAnalysisResolver(
    langPath: base_path('lang'),
    locales: ['en', 'es', 'fr'],
    reportDynamic: true,
    vendorPath: base_path('vendor/*/lang'),
    ignore: ['debug.*', 'temp.*'],
    includePatterns: ['validation.*', 'auth.*']
);

$config->analysisResolver($translationResolver);
```

Features:

- Validates `trans()`, `__()`, and `Lang::get()` calls
- Supports multiple locales
- Handles PHP and JSON translation files
- Supports vendor package translations (namespaced keys)
- Pattern-based filtering with wildcards
- Reports dynamic keys and missing vendor packages

## Reporter

Customize output and reporting:

```php
use Cline\Analyzer\Contracts\ReporterInterface;
use Cline\Analyzer\Data\AnalysisResult;

class JsonReporter implements ReporterInterface
{
    public function start(array $files): void
    {
        echo json_encode(['status' => 'started', 'files' => count($files)]);
    }

    public function progress(AnalysisResult $result): void
    {
        echo json_encode([
            'file' => $result->file->getPathname(),
            'success' => $result->success,
        ]);
    }

    public function finish(array $results): void
    {
        echo json_encode(['status' => 'complete', 'results' => $results]);
    }
}

$config->reporter(new JsonReporter());
```

## Complete Custom Example

```php
$config = AnalyzerConfig::make()
    ->paths(['src'])
    ->pathResolver(new CustomPathResolver())
    ->fileResolver(new CustomFileResolver())
    ->analysisResolver(new CustomAnalysisResolver())
    ->reporter(new JsonReporter());
```

<a id="doc-docs-examples"></a>

## Basic Laravel Usage

### Artisan Command

```bash
# Quick check of your Laravel app
php artisan analyzer:analyze

# Analyze specific directories
php artisan analyzer:analyze app/Models app/Services

# With parallel processing
php artisan analyzer:analyze --parallel --workers=8

# Exclude vendor and storage directories
php artisan analyzer:analyze --exclude=vendor --exclude=storage
```

### AI Agent Mode

Generate XML prompts for automated fixing:

```bash
php artisan analyzer:analyze --agent
```

The output can be consumed by AI agents to automatically fix missing class references in parallel.

## CI/CD Integration

### GitHub Actions

```yaml
name: Code Analysis

on: [push, pull_request]

jobs:
  analyze:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-dev --prefer-dist
      - run: php artisan analyzer:analyze --parallel
```

### GitLab CI

```yaml
analyze:
  image: php:8.4-cli
  script:
    - composer install --no-dev --prefer-dist
    - php artisan analyzer:analyze --parallel
```

## Programmatic Laravel Integration

```php
use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;

$config = AnalyzerConfig::make()
    ->paths([
        app_path(),
        base_path('tests'),
    ])
    ->ignore([
        'Illuminate\\*',
        'Laravel\\*',
    ])
    ->exclude([
        'vendor',
        'storage',
    ])
    ->workers(4);

$analyzer = new Analyzer($config);
$results = $analyzer->analyze();
```

## Custom Ignore and Exclude Patterns

```php
$config = AnalyzerConfig::make()
    ->paths(['src'])
    ->ignore([
        'Illuminate\\*',           // Laravel framework
        'Symfony\\Component\\*',   // Symfony components
        'PHPUnit\\*',              // PHPUnit framework
        'Mockery\\*',              // Mockery mocking
        'App\\Generated\\*',       // Generated code
    ])
    ->exclude([
        'vendor',                  // Skip vendor directory
        'node_modules',            // Skip node_modules
        'storage',                 // Skip storage directory
        'build',                   // Skip build artifacts
        '*.blade.php',             // Skip Blade templates
        'tests/fixtures/*',        // Skip test fixtures
    ]);
```

## JSON Output Reporter

Create a custom JSON reporter:

```php
use Cline\Analyzer\Contracts\ReporterInterface;
use Cline\Analyzer\Data\AnalysisResult;

class JsonFileReporter implements ReporterInterface
{
    private array $data = [];

    public function start(array $files): void
    {
        $this->data = ['files' => count($files), 'results' => []];
    }

    public function progress(AnalysisResult $result): void
    {
        $this->data['results'][] = [
            'file' => $result->file->getPathname(),
            'success' => $result->success,
            'missing' => $result->missing,
        ];
    }

    public function finish(array $results): void
    {
        file_put_contents(
            'analysis-report.json',
            json_encode($this->data, JSON_PRETTY_PRINT)
        );
    }
}

$config->reporter(new JsonFileReporter());
```

## Composer Script

Add to `composer.json`:

```json
{
    "scripts": {
        "analyze": "php artisan analyzer:analyze --parallel",
        "test": [
            "@analyze",
            "pest"
        ]
    }
}
```

Run with:

```bash
composer analyze
```

## Pre-commit Hook

Create `.git/hooks/pre-commit`:

```bash
#!/bin/bash
php artisan analyzer:analyze --parallel
if [ $? -ne 0 ]; then
    echo "Analysis failed. Commit aborted."
    exit 1
fi
```

## Multiple Configurations

Create environment-specific configs:

```php
// config/analyzer-dev.php
return AnalyzerConfig::make()
    ->paths(['app'])
    ->exclude(['vendor', 'storage'])
    ->workers(2);

// config/analyzer-ci.php
return AnalyzerConfig::make()
    ->paths(['app', 'tests'])
    ->workers(8)
    ->ignore(['Tests\\Fixtures\\*'])
    ->exclude(['vendor', 'node_modules', 'storage']);
```

## Route Analysis

Validate route names in your Laravel application:

```php
use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;
use Cline\Analyzer\Resolvers\RouteAnalysisResolver;

$routeResolver = new RouteAnalysisResolver(
    routesPath: base_path('routes'),
    cacheRoutes: true,
    cacheTtl: 3600,
    reportDynamic: true,
    includePatterns: ['admin.*', 'api.*'],
    ignorePatterns: ['debug.*'],
    app: app()
);

$config = AnalyzerConfig::make()
    ->paths(['app', 'resources/views'])
    ->analysisResolver($routeResolver);

$analyzer = new Analyzer($config);
$results = $analyzer->analyze();
```

Or via command:

```bash
php artisan analyzer:analyze --route
```

## Translation Analysis

Validate translation keys in your Laravel application:

```php
use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;
use Cline\Analyzer\Resolvers\TranslationAnalysisResolver;

$translationResolver = new TranslationAnalysisResolver(
    langPath: base_path('lang'),
    locales: ['en', 'es', 'fr'],
    reportDynamic: true,
    vendorPath: base_path('vendor/*/lang'),
    ignore: ['debug.*', 'temp.*'],
    includePatterns: ['validation.*', 'auth.*']
);

$config = AnalyzerConfig::make()
    ->paths(['app', 'resources/views'])
    ->analysisResolver($translationResolver);

$analyzer = new Analyzer($config);
$results = $analyzer->analyze();
```

Or via command:

```bash
php artisan analyzer:analyze --lang
```

## Programmatic API

```php
use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;

class MyAnalyzer
{
    public function run(): bool
    {
        $config = AnalyzerConfig::make()
            ->paths($this->getPaths())
            ->ignore($this->getIgnorePatterns())
            ->exclude($this->getExcludePatterns());

        $analyzer = new Analyzer($config);
        $results = $analyzer->analyze();

        return !$analyzer->hasFailures($results);
    }

    private function getPaths(): array
    {
        return ['src', 'app'];
    }

    private function getIgnorePatterns(): array
    {
        return ['Vendor\\*'];
    }

    private function getExcludePatterns(): array
    {
        return ['vendor', 'storage', 'cache'];
    }
}
```

<a id="doc-docs-parallel-processing"></a>

## Configuring Workers

The `workers` parameter controls how many files are processed concurrently:

```php
// Auto-detect CPU cores (default)
$config = AnalyzerConfig::make()
    ->paths(['src', 'tests'])
    ->workers(0);  // 0 = auto-detect

// Specify exact worker count
$config->workers(2);   // 2 workers (smaller machines)
$config->workers(4);   // 4 workers (balanced)
$config->workers(8);   // 8 workers (large codebases)
```

## Auto-Detection

When `workers` is set to `0` or `'auto'`, the analyzer automatically detects your CPU core count:

- **macOS**: Uses `sysctl -n hw.ncpu`
- **Linux**: Uses `nproc`
- **Fallback**: 4 cores if detection fails

```php
use Cline\Analyzer\Actions\DetectCoreCount;

$cores = (new DetectCoreCount())();  // Returns detected core count
```

## Command-Line Override

```bash
# Auto-detect cores
php artisan analyzer:analyze --workers=auto

# Specify worker count
php artisan analyzer:analyze --workers=8

# Use serial processing (1 worker)
php artisan analyzer:analyze --serial
```

## Serial Processing

Use `SerialProcessor` for single-threaded execution:

```php
use Cline\Analyzer\Processors\SerialProcessor;

$config = AnalyzerConfig::make()
    ->processor(new SerialProcessor());
```

This processes files sequentially, which can be useful for:

- Debugging issues
- Consistent output ordering
- Memory-constrained systems
- Small codebases (<50 files)

## Performance Considerations

### When to Use Parallel Processing

- Large codebases (100+ files)
- Modern multi-core systems
- Production CI/CD pipelines
- Regular development workflow

### When to Use Single-Threaded

- Small codebases (<50 files)
- Debugging analysis issues
- Memory-limited environments
- Systems without process support

## Benchmarks

Example performance on a 1000-file codebase:

| Workers | Time |
|---------|------|
| Single-threaded | ~45 seconds |
| 2 workers | ~25 seconds |
| 4 workers | ~15 seconds |
| 8 workers | ~12 seconds |

## Memory Usage

Parallel processing uses more memory:

| Workers | Memory |
|---------|--------|
| Single-threaded | ~50MB baseline |
| 4 workers | ~200MB total |
| 8 workers | ~400MB total |

Plan worker count based on available system memory.

## Adaptive Configuration

```php
// Adaptive worker count based on environment
$workers = match (true) {
    getenv('CI') === 'true' => 0,        // Auto-detect in CI
    PHP_OS_FAMILY === 'Windows' => 2,    // Conservative for Windows
    default => 0,                         // Auto-detect for development
};

$config = AnalyzerConfig::make()
    ->workers($workers);
```

## Custom Core Detection

```php
use Cline\Analyzer\Actions\DetectCoreCount;

// Get detected core count
$detector = new DetectCoreCount();
$cores = $detector();

// Use 50% of available cores
$workers = max(1, (int) ($cores / 2));

$config = AnalyzerConfig::make()
    ->workers($workers);
```
