# Getting Started

## Installation

```bash
composer require cline/analyzer
```

The package will be automatically registered via Laravel's package discovery.

## Basic Usage

### Using Artisan Command (Recommended)

The simplest way to use the analyzer in Laravel:

```bash
# Analyze default paths (app, tests)
php artisan analyzer:analyze

# Analyze specific paths
php artisan analyzer:analyze app/Models app/Services

# Enable parallel processing
php artisan analyzer:analyze --parallel --workers=8

# AI agent mode for automated fixing
php artisan analyzer:analyze --agent
```

### Programmatic Usage

You can also use the analyzer programmatically in your Laravel application:

```php
<?php

use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;

$config = AnalyzerConfig::make()
    ->paths(['app', 'tests'])
    ->parallel(workers: 4);

$analyzer = new Analyzer($config);
$results = $analyzer->analyze();

exit($analyzer->hasFailures($results) ? 1 : 0);
```

## Configuration

The analyzer is configured using the fluent `AnalyzerConfig` API:

```php
$config = AnalyzerConfig::make()
    ->paths(['src', 'tests'])           // Directories to analyze
    ->parallel(workers: 4, enabled: true) // Enable parallel processing
    ->ignore(['Illuminate\\*']);         // Ignore patterns
```

## Output Modes

### Human-Readable Output (Default)

The analyzer uses Laravel Prompts for beautiful terminal output:

- Progress indicators during analysis
- Summary statistics (total files, missing classes, top broken namespaces)
- Detailed failure reports with tables
- Color-coded messages for easy scanning

### AI Agent Mode

Use `--agent` flag or `->agentMode()` for XML-structured orchestration prompts:

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

- `0`: All class references exist
- `1`: Missing class references found

## Configuration File

Publish the configuration:

```bash
php artisan vendor:publish --tag=analyzer-config
```

Edit `analyzer.php` in your project root:

```php
<?php

use Cline\Analyzer\Config\AnalyzerConfig;

return AnalyzerConfig::make()
    ->paths(['app', 'tests'])
    ->parallel(workers: 4)
    ->ignore(['Illuminate\\*', 'Symfony\\*']);
```
