# Parallel Processing

The analyzer supports parallel file processing for improved performance on large codebases.

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

### Auto-Detection

When `workers` is set to `0` or `'auto'`, the analyzer automatically detects your CPU core count:

- **macOS**: Uses `sysctl -n hw.ncpu`
- **Linux**: Uses `nproc`
- **Fallback**: 4 cores if detection fails

```php
use Cline\Analyzer\Actions\DetectCoreCount;

$cores = (new DetectCoreCount())();  // Returns detected core count
```

### Command-Line Override

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

✅ Large codebases (100+ files)
✅ Modern multi-core systems
✅ Production CI/CD pipelines
✅ Regular development workflow

### When to Use Single-Threaded

✅ Small codebases (<50 files)
✅ Debugging analysis issues
✅ Memory-limited environments
✅ Systems without process support

## Benchmarks

Example performance on a 1000-file codebase:

- Single-threaded: ~45 seconds
- 2 workers: ~25 seconds
- 4 workers: ~15 seconds
- 8 workers: ~12 seconds

## Memory Usage

Parallel processing uses more memory:

- Single-threaded: ~50MB baseline
- 4 workers: ~200MB total
- 8 workers: ~400MB total

Plan worker count based on available system memory.

## Example: Adaptive Configuration

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

## Example: Custom Core Detection

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
