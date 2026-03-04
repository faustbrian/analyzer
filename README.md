# Analyzer

Configurable parallel PHP code analyzer for checking class references with Laravel Prompts UI and AI agent orchestration.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/) and Laravel 12+**

## Installation

```bash
composer require cline/analyzer
```

The service provider will be automatically registered via Laravel's package discovery.

## Features

- **Laravel Artisan Command**: `php artisan analyzer:analyze` for easy CLI usage
- **AI Agent Mode**: Generate XML-structured prompts for parallel AI-powered fixes
- **Configurable Architecture**: Replace core components via interfaces
- **Parallel Processing**: Analyze files concurrently with configurable worker count
- **Laravel Prompts UI**: Beautiful terminal reporting with summary statistics
- **Flexible Resolution**: Custom path, file, and analysis resolvers
- **Based on graham-analyzer**: Built on battle-tested analysis logic

## Usage

### Artisan Command

```bash
# Analyze default paths (app, tests) with auto-detected CPU cores
php artisan analyzer:analyze

# Analyze specific paths
php artisan analyzer:analyze src tests

# Auto-detect CPU cores for parallel processing
php artisan analyzer:analyze --workers=auto

# Specify exact worker count
php artisan analyzer:analyze --workers=8

# Ignore specific class patterns
php artisan analyzer:analyze --ignore="Illuminate\\*" --ignore="Symfony\\*"

# Exclude files/directories from scanning
php artisan analyzer:analyze --exclude=vendor --exclude=storage

# AI agent mode - outputs XML prompts for automated fixing
php artisan analyzer:analyze --agent
```

### Programmatic Usage

```php
use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;

$config = AnalyzerConfig::make()
    ->paths(['app', 'tests'])
    ->workers(0)  // 0 = auto-detect CPU cores
    ->ignore(['Illuminate\\*'])
    ->exclude(['vendor', 'storage']);

$analyzer = new Analyzer($config);
$results = $analyzer->analyze();
```

### AI Agent Mode

Generate structured prompts for spawning parallel AI agents to fix issues:

```php
$config = AnalyzerConfig::make()
    ->paths(['app'])
    ->agentMode();

$analyzer = new Analyzer($config);
$analyzer->analyze();
```

This outputs XML-structured orchestration prompts grouped by namespace for efficient parallel processing.

### Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=analyzer-config
```

This creates `analyzer.php` in your project root:

```php
<?php

use Cline\Analyzer\Config\AnalyzerConfig;

return AnalyzerConfig::make()
    ->paths(['app', 'tests'])
    ->workers(0)  // 0 = auto-detect CPU cores, or specify (e.g., 4, 8)
    ->ignore(['Illuminate\\*', 'Symfony\\*'])
    ->exclude(['vendor', 'node_modules', 'storage']);
```

### Custom Resolvers

Implement custom resolution logic:

```php
use Cline\Analyzer\Contracts\PathResolverInterface;

class CustomPathResolver implements PathResolverInterface
{
    public function resolve(array $paths): array
    {
        // Custom path resolution logic
        return $resolvedPaths;
    }
}
```

## Documentation

- **[Getting Started](https://docs.cline.sh/analyzer/getting-started/)** - Installation and basic usage
- **[Configuration](https://docs.cline.sh/analyzer/configuration/)** - Paths, workers, ignore, and exclude patterns
- **[Parallel Processing](https://docs.cline.sh/analyzer/parallel-processing/)** - Worker configuration and benchmarks
- **[Custom Resolvers](https://docs.cline.sh/analyzer/custom-resolvers/)** - Path, file, analysis, and reporter interfaces
- **[Examples](https://docs.cline.sh/analyzer/examples/)** - CI/CD, pre-commit hooks, route and translation analysis

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form](https://github.com/faustbrian/analyzer/security) rather than the issue queue.

## Credits

- [Brian Faust](https://github.com/faustbrian)
- Based on [graham-campbell/analyzer](https://github.com/GrahamCampbell/Analyzer)
- [All Contributors](../../contributors)

## License

The MIT License. Please see [License File](LICENSE.md) for more information.
