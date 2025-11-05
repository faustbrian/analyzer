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
    ->parallel(workers: 2)
    ->ignore(['Test\\*'])
    ->pathResolver(new CustomPathResolver());
```
