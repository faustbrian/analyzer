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
