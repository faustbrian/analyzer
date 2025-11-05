# Configuration

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

// Command-line override
php artisan analyzer:analyze --workers=auto    // Auto-detect
php artisan analyzer:analyze --workers=8       // Use 8 workers
```

The analyzer automatically detects your CPU core count when `workers` is set to `0` or `'auto'`. This uses:
- macOS: `sysctl -n hw.ncpu`
- Linux: `nproc`
- Fallback: 4 cores if detection fails

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

## Loading Configuration

Load from a file:

```php
$config = require __DIR__.'/analyzer.php';
$analyzer = new Analyzer($config);
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
