# Translation Analyzer Implementation Plan

## Overview
Analyze PHP and Blade files to detect invalid `trans()`, `__()`, and `@lang()` calls that reference non-existent translation keys.

## Architecture

### 1. TranslationCallVisitor (AST Visitor)
**Purpose**: Extract translation key references from PHP AST

**Detects**:
- `trans('validation.required')`
- `__('auth.failed')`
- `Lang::get('messages.welcome')`
- `@lang('errors.404')` (after Blade compilation)

**Returns**: Array of translation keys with file location metadata

### 2. BladeParser
**Purpose**: Convert Blade templates to parseable PHP

**Approach**:
```php
use Illuminate\View\Compilers\BladeCompiler;

$compiler = new BladeCompiler(...);
$php = $compiler->compileString($bladeContents);
// Pass to existing PHP parser
```

**Handles**:
- `{{ __('key') }}`
- `@lang('key')`
- `{{ trans('key') }}`

### 3. TranslationAnalysisResolver
**Purpose**: Validate extracted keys against actual translation files

**Implementation**:
```php
final readonly class TranslationAnalysisResolver implements AnalysisResolverInterface
{
    private array $translationKeys;

    public function __construct(
        private string $langPath,
        private array $locales = ['en']
    ) {
        $this->translationKeys = $this->loadTranslations();
    }

    private function loadTranslations(): array
    {
        // Load from lang/{locale}/*.php
        // Build nested key map: ['validation.required' => true, ...]
    }

    public function analyze(SplFileInfo $file): AnalysisResult
    {
        // Parse file, extract keys, validate against $this->translationKeys
    }
}
```

### 4. File Type Detection
**Extend FileResolver** to handle `.blade.php` files:
```php
if (str_ends_with($file, '.blade.php')) {
    $content = $bladeParser->parse($content);
}
```

## Validation Logic

### Static Keys (Easy)
```php
trans('validation.required') // ✓ Can validate
__('auth.failed')            // ✓ Can validate
```

### Dynamic Keys (Hard)
```php
trans($variable)                      // ⚠️ Warning: can't validate
trans('prefix.' . $suffix)            // ⚠️ Warning: dynamic
trans(config('app.message_key'))      // ⚠️ Warning: uses config
```

**Strategy**: Report dynamic calls separately with warning level

## Translation Key Loading

### Laravel Path Conventions
1. **New**: `lang/{locale}/*.php`
2. **Old**: `resources/lang/{locale}/*.php`
3. **JSON**: `lang/{locale}.json`

### Key Building
```php
// lang/en/validation.php returns ['required' => '...']
// Becomes: validation.required

// lang/en.json contains {"Welcome": "Welcome"}
// Becomes: Welcome (no prefix)
```

## Configuration

Add to `config/analyzer.php`:
```php
'translation' => [
    'enabled' => true,
    'lang_path' => base_path('lang'),
    'locales' => ['en'], // Which locales to validate against
    'report_dynamic' => true, // Report dynamic key usage as warnings
    'legacy_path' => base_path('resources/lang'), // Fallback for old structure
],
```

## Testing Strategy

### Unit Tests
- `TranslationCallVisitor` extracts keys correctly
- `BladeParser` compiles Blade to valid PHP
- `TranslationAnalysisResolver` loads lang files correctly
- Key validation logic (nested keys, JSON files)

### Integration Tests
- Analyze PHP file with `trans()` calls
- Analyze Blade file with `@lang()` directives
- Test with multiple locales
- Test dynamic key detection

### Fixtures
```
tests/Fixtures/translations/
  lang/en/validation.php
  lang/en/auth.php
  lang/en.json
  views/example.blade.php
  php/TranslationUsage.php
```

## Implementation Order

1. ✅ Create `TranslationCallVisitor` (PHP only)
2. ✅ Create `TranslationAnalysisResolver` with file loading
3. ✅ Write tests for PHP file analysis
4. ✅ Add `BladeParser` support
5. ✅ Write tests for Blade file analysis
6. ✅ Add dynamic key detection with warnings
7. ✅ Add multi-locale support
8. ✅ Integration with existing Analyzer

## Edge Cases

- **Namespaced translations**: `package::file.key`
- **Array notation**: `trans('validation.attributes.email')`
- **Vendor translations**: Load from `vendor/{package}/lang/`
- **Fallback locale**: Check `config('app.fallback_locale')`
- **Translation groups**: `trans('validation')` returns entire group
- **JSON translations**: Flat structure vs nested PHP arrays

## Performance Considerations

- Cache loaded translation files (don't reload per file analyzed)
- Lazy load translations only when analyzing files with translation calls
- Parallel processing: share translation data across workers
