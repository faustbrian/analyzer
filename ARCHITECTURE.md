# Architecture

## Overview

The Analyzer package is a configurable, parallel PHP code analyzer built on top of graham-analyzer's battle-tested analysis logic. It transforms the test-suite-based approach into a standalone, configurable tool with Laravel Prompts UI.

## Core Components

### 1. Configuration (`Config/`)

- **AnalyzerConfig**: Immutable fluent configuration builder
  - Paths to analyze
  - Parallel processing settings
  - Ignore patterns
  - Custom resolver injection

### 2. Contracts (`Contracts/`)

Four key interfaces for customization:

- **PathResolverInterface**: Controls which paths are analyzed
- **FileResolverInterface**: Determines which files to analyze
- **AnalysisResolverInterface**: Handles file analysis and class checking
- **ReporterInterface**: Manages output and reporting

### 3. Analysis Engine (`Analysis/`)

Ported from graham-analyzer:

- **ReferenceAnalyzer**: Main analysis coordinator
- **ClassInspector**: Class/interface/trait existence checks
- **DocProcessor**: PHPDoc processing
- **DocVisitor**: AST doc comment visitor
- **ImportVisitor**: Import statement visitor
- **NameVisitor**: Fully-qualified name visitor

Uses `nikic/php-parser` for AST parsing and `phpdocumentor/*` for doc analysis.

### 4. Resolvers (`Resolvers/`)

Default implementations:

- **PathResolver**: Validates paths exist
- **FileResolver**: Discovers PHP files recursively
- **AnalysisResolver**: Analyzes files and applies ignore patterns

### 5. Parallel Processing (`Parallel/`)

- **ProcessPool**: Manages parallel file processing
  - Configurable worker count
  - Falls back to serial processing
  - Chunks files across workers

### 6. Reporting (`Reporters/`)

- **PromptsReporter**: Laravel Prompts UI
  - Progress indicators
  - Color-coded output
  - Tabular failure reports

### 7. Data Transfer Objects (`Data/`)

- **AnalysisResult**: Immutable result container
  - File reference
  - Found references
  - Missing classes
  - Success/failure status

## Data Flow

```
Configuration
    ↓
PathResolver → resolves paths
    ↓
FileResolver → discovers files
    ↓
ProcessPool → distributes work
    ↓
AnalysisResolver → analyzes each file
    ↓
ReferenceAnalyzer → extracts references
    ↓
ClassInspector → checks existence
    ↓
AnalysisResult → result per file
    ↓
Reporter → displays output
```

## Design Principles

### Immutability

- Config objects are immutable
- Data objects are readonly
- Fluent API returns new instances

### Dependency Injection

All core components injectable via interfaces:

```php
$config->pathResolver(new CustomPathResolver())
    ->fileResolver(new CustomFileResolver())
    ->analysisResolver(new CustomAnalysisResolver())
    ->reporter(new CustomReporter());
```

### Single Responsibility

Each component has one clear purpose:

- Resolvers: Discovery and filtering
- Analyzer: Orchestration
- Analysis: Reference extraction
- Reporter: Output formatting

### Configurability

Everything configurable without subclassing:

- Ignore patterns via config
- Custom resolvers via interfaces
- Parallel workers via config
- Output format via reporter

## Extension Points

### Custom Path Discovery

```php
class GitignorePathResolver implements PathResolverInterface
{
    public function resolve(array $paths): array
    {
        // Respect .gitignore patterns
    }
}
```

### Custom File Filtering

```php
class ModifiedFilesResolver implements FileResolverInterface
{
    public function shouldAnalyze(SplFileInfo $file): bool
    {
        // Only analyze git-modified files
    }
}
```

### Custom Analysis Logic

```php
class StrictAnalysisResolver implements AnalysisResolverInterface
{
    public function classExists(string $class): bool
    {
        // Stricter existence checks
    }
}
```

### Custom Reporting

```php
class JsonReporter implements ReporterInterface
{
    // JSON output for CI/CD integration
}
```

## Performance

### Parallel Processing

- Splits files across workers
- Each worker processes chunk
- Results merged at completion
- Configurable worker count

### Memory Management

- Streams file discovery
- Processes files individually
- No bulk loading
- Lazy evaluation where possible

## Testing Strategy

### Unit Tests

- Individual component testing
- Mock dependencies
- Fast execution
- High coverage

### Feature Tests

- End-to-end workflows
- Real file analysis
- Integration verification
- Both parallel and serial modes

### Fixtures

- ValidClass.php: Passes analysis
- InvalidClass.php: Fails with missing classes
- Tests both success and failure paths

## Dependencies

### Core

- `nikic/php-parser`: AST parsing
- `phpdocumentor/*`: Doc analysis
- `laravel/prompts`: Terminal UI
- `symfony/process`: Parallel execution
- `illuminate/support`: Collection helpers

### Development

- `pestphp/pest`: Testing framework
- `phpstan/phpstan`: Static analysis
- `rector/rector`: Code refactoring
- Laravel testing tools

## Future Enhancements

Potential additions:

1. **Progress Streaming**: Real-time file progress
2. **Caching**: Skip unchanged files
3. **Watch Mode**: Continuous analysis
4. **IDE Integration**: Editor plugins
5. **Custom Visitors**: User-defined AST visitors
6. **Plugin System**: Extensible analysis rules
7. **Report Formats**: JSON, XML, HTML output
8. **Performance Metrics**: Analysis timing data
