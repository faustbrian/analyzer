# Translation Analyzer Test Coverage

This document outlines the comprehensive test suite created for the translation analyzer components using TDD approach.

## Test Files Created

### 1. TranslationCallVisitorTest.php
**Location**: `tests/Unit/TranslationCallVisitorTest.php`

**Purpose**: Tests the AST visitor that extracts translation keys from PHP code.

**Test Categories**:

#### Happy Path - Static Key Detection
- Extracts `trans()` calls with static strings
- Extracts `__()` calls with static strings
- Extracts `Lang::get()` calls with static strings
- Extracts `@lang()` from compiled Blade templates
- Handles nested translation keys with multiple dots

#### Sad Path - Dynamic Key Detection
- Detects dynamic keys using variables
- Detects dynamic keys using concatenation
- Detects dynamic keys using `config()` calls
- Detects dynamic keys using method calls
- Detects dynamic keys using ternary operators

#### Edge Cases
- Handles `trans()` with replacement parameters
- Handles `trans_choice()` calls
- Handles namespaced package translations (`package::file.key`)
- Handles empty translation key strings
- Ignores `trans()` calls without arguments
- Handles multiple translation calls in same file
- Handles unicode characters in translation keys
- Resets state between traversals

#### JSON Translation Support
- Detects JSON translation keys without file prefix
- Distinguishes between file-based and JSON keys

**Total Test Count**: ~25 tests

---

### 2. BladeParserTest.php
**Location**: `tests/Unit/BladeParserTest.php`

**Purpose**: Tests Blade template compilation to parseable PHP.

**Test Categories**:

#### Happy Path - Basic Blade Directives
- Compiles `@lang` directive to valid PHP
- Compiles `{{ __() }}` to valid PHP
- Compiles `{{ trans() }}` to valid PHP
- Compiles multiple translation calls in same template

#### Happy Path - Complex Blade Templates
- Preserves translation calls in conditional blocks
- Preserves translation calls in loops
- Handles translation calls with replacement parameters
- Handles translation calls as function arguments

#### Edge Cases
- Handles empty Blade template
- Handles Blade with no translation calls
- Handles nested Blade components with translations
- Handles Blade comments with translation examples
- Handles escaped Blade syntax (`@{{ }}`)
- Handles unicode in Blade translations
- Handles multiline Blade translation calls

#### Blade Parser Configuration
- Can parse Blade from file path
- Throws exception for non-existent Blade file
- Handles Blade with syntax errors gracefully

#### Blade to PHP AST Compatibility
- Produces parseable PHP for AST analysis
- Maintains line number information for error reporting

#### JSON Translation in Blade
- Handles JSON-style translations in Blade
- Handles mixed file-based and JSON translations

#### Namespaced Translations in Blade
- Handles package namespaced translations
- Preserves double-colon syntax in compiled output

**Total Test Count**: ~25 tests

---

### 3. TranslationAnalysisResolverTest.php
**Location**: `tests/Unit/TranslationAnalysisResolverTest.php`

**Purpose**: Tests translation key validation against actual translation files.

**Test Categories**:

#### Translation File Loading
- Loads PHP translation files from lang directory
- Loads JSON translation files
- Builds nested keys correctly
- Loads translations from multiple locales
- Handles missing locale directories gracefully
- Caches loaded translations

#### PHP File Analysis - Happy Path
- Validates PHP file with all valid translation keys
- Reports invalid translation keys
- Validates nested translation keys
- Validates JSON translation keys

#### Blade File Analysis - Happy Path
- Validates Blade file with valid translation keys
- Reports invalid keys in Blade files
- Handles mixed valid and invalid keys in Blade

#### Dynamic Key Detection
- Reports dynamic keys as warnings
- Handles concatenated translation keys
- Detects config-based translation keys

#### Edge Cases
- Handles empty translation file
- Handles file with no translation calls
- Handles syntax errors in source files
- Handles empty translation keys
- Handles unicode translation keys

#### Namespaced Package Translations
- Validates namespaced package translations
- Reports missing namespaced translations
- Handles missing vendor packages gracefully

#### Multi-Locale Validation
- Validates keys against all configured locales
- Reports keys missing in all locales
- Can validate against single locale only

#### Legacy Path Support
- Loads translations from `resources/lang` directory
- Prefers new `lang/` over `resources/lang/`

#### Performance
- Loads translations only once per resolver instance
- Handles large translation files efficiently

#### Integration with AnalysisResult
- Returns proper AnalysisResult structure
- Uses AnalysisResult factory methods

#### Configuration Options
- Respects `reportDynamic` configuration
- Respects ignore patterns
- Can validate only specific namespaces

**Total Test Count**: ~35 tests

---

## Test Fixtures

### Translation Files

#### English Locale (`lang/en/`)
- **validation.php**: Standard Laravel validation messages with nested keys
- **auth.php**: Authentication messages
- **messages.php**: General application messages with nested structure
- **passwords.php**: Password reset messages
- **errors.php**: Error messages with deeply nested keys
- **users.php**: User-related translations
- **greetings.php**: Unicode translation keys

#### Spanish Locale (`lang/es/`)
- **errors.php**: Spanish error messages
- **messages.php**: Spanish messages including locale-specific keys

#### French Locale (`lang/fr/`)
- **messages.php**: French messages including locale-specific keys

#### JSON Translations
- **en.json**: JSON-format translations (flat structure)

#### Legacy Path (`resources/lang/en/`)
- **legacy.php**: Translations in old Laravel path structure

#### Large Dataset (`large/en/`)
- **large.php**: 1000+ translation keys for performance testing

#### Vendor Packages (`vendor/`)
- **package/lang/en/messages.php**: Namespaced package translations
- **package/lang/en/info.php**: Package info translations
- **vendor-package/lang/en/errors.php**: Another package translations

---

### PHP Fixture Files (`php/`)

All fixture files follow proper PHP structure and namespace conventions:

1. **ValidTranslations.php**: Only valid translation keys
2. **InvalidTranslations.php**: Mix of valid and invalid keys
3. **NestedTranslations.php**: Deeply nested key references
4. **JsonTranslations.php**: JSON-style translation usage
5. **DynamicKeys.php**: Variable-based translation keys
6. **ConcatenatedKeys.php**: String concatenation in keys
7. **ConfigKeys.php**: Config-based translation keys
8. **EmptyFile.php**: No translation calls
9. **NoTranslations.php**: PHP class without translations
10. **EmptyKeys.php**: Empty string translation keys
11. **UnicodeKeys.php**: Unicode characters in keys
12. **PackageTranslations.php**: Namespaced package translation usage
13. **InvalidPackageTranslations.php**: Missing package translations
14. **MissingVendorTranslations.php**: Non-existent vendor packages
15. **MultiLocale.php**: Keys existing in multiple locales
16. **MissingInAllLocales.php**: Keys missing in all configured locales
17. **SpanishOnly.php**: Keys only in Spanish locale
18. **ManyTranslations.php**: File with 20+ translation calls
19. **IgnoredKeys.php**: Keys matching ignore patterns
20. **MixedNamespaces.php**: Multiple translation namespaces
21. **SyntaxError.php**: Intentional syntax error for error handling tests

---

### Blade Template Fixtures (`views/`)

1. **example.blade.php**: Basic Blade with all translation directive types
2. **valid.blade.php**: Complete HTML template with only valid keys
3. **invalid.blade.php**: Template with missing translation keys
4. **mixed.blade.php**: Template with both valid and invalid keys

---

## Test Execution Strategy

### Running Tests

```bash
# Run all translation tests
./vendor/bin/pest tests/Unit/TranslationCallVisitorTest.php
./vendor/bin/pest tests/Unit/BladeParserTest.php
./vendor/bin/pest tests/Unit/TranslationAnalysisResolverTest.php

# Run with coverage
./vendor/bin/pest --coverage

# Run specific test group
./vendor/bin/pest --group=translation
```

### Test Organization

All tests follow the **Arrange-Act-Assert (AAA)** pattern:

```php
test('it extracts trans() calls with static strings', function (): void {
    // Arrange
    $code = <<<'PHP'
    <?php
    trans('validation.required');
    PHP;

    $ast = $this->parser->parse($code);

    // Act
    $this->traverser->traverse($ast);
    $calls = $this->visitor->getTranslationCalls();

    // Assert
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['key'])->toBe('validation.required');
});
```

---

## Coverage Analysis

### Components Tested

1. **TranslationCallVisitor** (AST Visitor)
   - Static key extraction
   - Dynamic key detection
   - All translation function types
   - Edge cases and error handling

2. **BladeParser** (Blade Compiler)
   - Directive compilation
   - Template preservation
   - Error handling
   - AST compatibility

3. **TranslationAnalysisResolver** (Validator)
   - File loading and caching
   - Key validation
   - Multi-locale support
   - Configuration options
   - Performance optimization

### Test Coverage Goals

- **Line Coverage**: >95%
- **Branch Coverage**: >90%
- **Method Coverage**: 100%

### Testing Pyramid

```
        /\
       /  \
      / UI \     <- Feature Tests (Integration)
     /______\
    /        \
   / Service \   <- Unit Tests (Core Logic)
  /          \
 /____________\
```

**Unit Tests** (85 total):
- Test individual components in isolation
- Fast execution (<1 second total)
- No external dependencies

**Integration Tests** (Planned):
- Test complete workflow from file to result
- Test with real Laravel application structure
- Test parallel processing scenarios

---

## TDD Implementation Order

Following the plan from LANG.md:

1. ✅ **TranslationCallVisitor Tests** - Created with 25+ test cases
2. ✅ **BladeParser Tests** - Created with 25+ test cases
3. ✅ **TranslationAnalysisResolver Tests** - Created with 35+ test cases
4. ✅ **Comprehensive Fixtures** - 38 files across multiple categories
5. ⏭️ **Implement TranslationCallVisitor** - Next step
6. ⏭️ **Implement BladeParser** - Next step
7. ⏭️ **Implement TranslationAnalysisResolver** - Next step
8. ⏭️ **Integration Tests** - Final step

---

## Next Steps

### Implementation Phase

With all tests written, implement the actual classes:

1. **TranslationCallVisitor.php** in `src/Analysis/`
   - Extend `PhpParser\NodeVisitorAbstract`
   - Implement `enterNode()` to detect translation calls
   - Track static vs dynamic keys
   - Handle all translation function types

2. **BladeParser.php** in `src/Translation/`
   - Use `Illuminate\View\Compilers\BladeCompiler`
   - Compile Blade templates to parseable PHP
   - Preserve line numbers for error reporting

3. **TranslationAnalysisResolver.php** in `src/Translation/`
   - Implement `AnalysisResolverInterface`
   - Load translation files from configured paths
   - Validate keys against loaded translations
   - Support multi-locale validation

### Verification

Run tests after each implementation:

```bash
# After each class implementation
./vendor/bin/pest tests/Unit/TranslationCallVisitorTest.php
./vendor/bin/pest tests/Unit/BladeParserTest.php
./vendor/bin/pest tests/Unit/TranslationAnalysisResolverTest.php

# Verify all tests pass
./vendor/bin/pest
```

---

## Configuration Integration

Add to `config/analyzer.php`:

```php
'translation' => [
    'enabled' => true,
    'lang_path' => base_path('lang'),
    'legacy_path' => base_path('resources/lang'),
    'locales' => ['en'],
    'report_dynamic' => true,
    'ignore' => [],
    'only_namespaces' => null,
    'vendor_path' => base_path('vendor'),
],
```

---

## Documentation

All test files include:
- Clear test descriptions using Pest's `describe()` and `test()` syntax
- Comprehensive comments explaining test scenarios
- Expected behaviors documented inline
- Edge cases clearly marked

## Success Criteria

✅ All test files created
✅ All fixture files created
✅ Tests cover happy paths, sad paths, and edge cases
✅ Tests follow existing project patterns
✅ Fixtures support all test scenarios
✅ Documentation complete

**Ready for TDD implementation!**
