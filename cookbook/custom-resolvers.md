# Custom Resolvers

The analyzer provides four resolver interfaces for customization:

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
