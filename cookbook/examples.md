# Examples

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

GitHub Actions workflow for Laravel:

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

## Programmatic Laravel Integration

Use within your Laravel application:

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

`.git/hooks/pre-commit`:

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

Use in commands:

```bash
# Development
php artisan analyzer:analyze app --workers=2

# CI
php artisan analyzer:analyze app tests --parallel --workers=8
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

### Route Analysis Options

```php
$routeResolver = new RouteAnalysisResolver(
    routesPath: base_path('routes'),        // Path to routes directory
    cacheRoutes: true,                      // Enable route caching
    cacheTtl: 3600,                         // Cache TTL in seconds
    reportDynamic: true,                    // Report dynamic route names as warnings
    includePatterns: ['admin.*', 'api.*'],  // Only validate these patterns
    ignorePatterns: ['debug.*', 'temp.*'],  // Ignore these patterns
    app: app()                              // Laravel application instance
);
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

### Translation Analysis Options

```php
$translationResolver = new TranslationAnalysisResolver(
    langPath: base_path('lang'),               // Path to lang directory
    locales: ['en', 'es', 'fr'],              // Locales to validate
    reportDynamic: true,                       // Report dynamic keys as warnings
    vendorPath: base_path('vendor/*/lang'),   // Path to vendor translations
    ignore: ['debug.*', 'temp.*'],            // Patterns to ignore
    includePatterns: ['validation.*']         // Only validate these patterns
);
```

## Programmatic Usage

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
