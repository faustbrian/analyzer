---
title: Custom Resolvers
description: Implement custom path, file, analysis, and reporting logic
---

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
