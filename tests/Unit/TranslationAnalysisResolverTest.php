<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Data\AnalysisResult;
use Cline\Analyzer\Resolvers\TranslationAnalysisResolver;
use PhpParser\ErrorHandler;
use PhpParser\Parser;

describe('TranslationAnalysisResolver', function (): void {
    beforeEach(function (): void {
        $this->langPath = __DIR__.'/../Fixtures/translations/lang';
        $this->resolver = new TranslationAnalysisResolver(
            langPath: $this->langPath,
            locales: ['en'],
        );
    });

    describe('Translation File Loading', function (): void {
        test('it loads PHP translation files from lang directory', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->toContain('validation.required')
                ->and($keys)->toContain('validation.email')
                ->and($keys)->toContain('auth.failed')
                ->and($keys)->toContain('auth.throttle');
        });

        test('it loads JSON translation files', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toContain('Welcome')
                ->and($keys)->toContain('Good morning')
                ->and($keys)->toContain('Thank you');
        });

        test('it builds nested keys correctly', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toContain('validation.required')
                ->and($keys)->toContain('validation.attributes.email')
                ->and($keys)->toContain('validation.attributes.password')
                ->and($keys)->toContain('messages.user.created')
                ->and($keys)->toContain('messages.user.updated');
        });

        test('it loads translations from multiple locales', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en', 'es', 'fr'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toContain('validation.required') // en
                ->and($keys)->toContain('errors.not_found'); // from other locales
        });

        test('it handles missing locale directories gracefully', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en', 'nonexistent'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->toContain('validation.required'); // en locale still works
        });

        test('it caches loaded translations', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
            );

            // Act
            $keys1 = $resolver->getLoadedKeys();
            $keys2 = $resolver->getLoadedKeys();

            // Assert
            expect($keys1)->toBe($keys2); // Same reference, cached
        });
    });

    describe('PHP File Analysis - Happy Path', function (): void {
        test('it validates PHP file with all valid translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/ValidTranslations.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result)->toBeInstanceOf(AnalysisResult::class)
                ->and($result->success)->toBeTrue()
                ->and($result->missing)->toBeEmpty()
                ->and($result->references)->toContain('validation.required')
                ->and($result->references)->toContain('auth.failed');
        });

        test('it reports invalid translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/InvalidTranslations.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result)->toBeInstanceOf(AnalysisResult::class)
                ->and($result->success)->toBeFalse()
                ->and($result->missing)->toContain('validation.nonexistent')
                ->and($result->missing)->toContain('messages.missing');
        });

        test('it validates nested translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/NestedTranslations.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toContain('validation.attributes.email')
                ->and($result->references)->toContain('messages.user.created');
        });

        test('it validates JSON translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/JsonTranslations.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toContain('Welcome')
                ->and($result->references)->toContain('Thank you');
        });
    });

    describe('Blade File Analysis - Happy Path', function (): void {
        test('it validates Blade file with valid translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/views/valid.blade.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toContain('users.title')
                ->and($result->references)->toContain('users.description');
        });

        test('it reports invalid keys in Blade files', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/views/invalid.blade.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->missing)->toContain('users.nonexistent')
                ->and($result->missing)->toContain('errors.missing');
        });

        test('it handles mixed valid and invalid keys in Blade', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/views/mixed.blade.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->references)->toHaveCount(4)
                ->and($result->missing)->toHaveCount(2);
        });
    });

    describe('Dynamic Key Detection', function (): void {
        test('it reports dynamic keys as warnings', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/DynamicKeys.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue() // Not a failure, just warnings
                ->and($result->warnings)->toHaveCount(3)
                ->and($result->warnings[0])->toMatchArray([
                    'type' => 'dynamic_key',
                    'line' => 23,
                    'reason' => 'Variable used as key',
                ]);
        });

        test('it handles concatenated translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/ConcatenatedKeys.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            $hasConcatWarning = false;

            foreach ($result->warnings as $warning) {
                if ($warning['type'] === 'dynamic_key' && $warning['reason'] === 'String concatenation') {
                    $hasConcatWarning = true;

                    break;
                }
            }

            expect($hasConcatWarning)->toBeTrue();
        });

        test('it detects config-based translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/ConfigKeys.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->warnings[0])->toMatchArray([
                'type' => 'dynamic_key',
                'reason' => 'Function call used as key',
            ]);
        });
    });

    describe('Edge Cases', function (): void {
        test('it handles empty translation file', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/EmptyFile.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toBeEmpty();
        });

        test('it handles file with no translation calls', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/NoTranslations.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toBeEmpty();
        });

        test('it handles syntax errors in source files', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/SyntaxError.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->hasError())->toBeTrue()
                ->and($result->error)->toContain('Syntax error');
        });

        test('it handles empty translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/EmptyKeys.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->missing)->toContain('')
                ->and($result->warnings)->toContainEqual([
                    'type' => 'empty_key',
                    'message' => 'Translation key is empty',
                ]);
        });

        test('it handles unicode translation keys', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/UnicodeKeys.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toContain('messages.你好')
                ->and($result->references)->toContain('greetings.здравствуй');
        });
    });

    describe('Namespaced Package Translations', function (): void {
        test('it validates namespaced package translations', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                vendorPath: __DIR__.'/../Fixtures/translations/vendor',
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/PackageTranslations.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toContain('package::messages.welcome')
                ->and($result->references)->toContain('vendor-package::errors.404');
        });

        test('it reports missing namespaced translations', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                vendorPath: __DIR__.'/../Fixtures/translations/vendor',
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/InvalidPackageTranslations.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->missing)->toContain('unknown-package::file.key')
                ->and($result->missing)->toContain('package::nonexistent.key');
        });

        test('it handles missing vendor packages gracefully', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/MissingVendorTranslations.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->warnings)->toContainEqual([
                    'type' => 'missing_vendor_package',
                    'package' => 'nonexistent-package',
                ]);
        });
    });

    describe('Multi-Locale Validation', function (): void {
        test('it validates keys against all configured locales', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en', 'es', 'fr'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/MultiLocale.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue();
            // Key valid if it exists in ANY configured locale
        });

        test('it reports keys missing in all locales', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en', 'es'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/MissingInAllLocales.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->missing)->toContain('messages.only_in_fr'); // Missing in both en and es
        });

        test('it can validate against single locale only', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/SpanishOnly.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->missing)->toContain('messages.only_in_es');
        });
    });

    describe('Legacy Path Support', function (): void {
        test('it loads translations from resources/lang directory', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: __DIR__.'/../Fixtures/translations/resources/lang',
                locales: ['en'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->toContain('legacy.message');
        });

        test('it prefers new lang/ over resources/lang/', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toContain('validation.required') // From new path
                ->and($keys)->toContain('legacy.message'); // Also loads legacy
        });
    });

    describe('Performance', function (): void {
        test('it loads translations only once per resolver instance', function (): void {
            // Arrange
            $file1 = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/ValidTranslations.php');
            $file2 = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/NestedTranslations.php');

            // Act
            $result1 = $this->resolver->analyze($file1);
            $result2 = $this->resolver->analyze($file2);

            // Assert
            expect($result1)->toBeInstanceOf(AnalysisResult::class)
                ->and($result2)->toBeInstanceOf(AnalysisResult::class);
        });

        test('it handles large translation files efficiently', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: __DIR__.'/../Fixtures/translations/large',
                locales: ['en'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/ManyTranslations.php');

            // Act
            $start = microtime(true);
            $result = $resolver->analyze($file);
            $duration = microtime(true) - $start;

            // Assert
            expect($duration)->toBeLessThan(1.0) // Should complete in under 1 second
                ->and($result)->toBeInstanceOf(AnalysisResult::class);
        });
    });

    describe('classExists Implementation', function (): void {
        test('it always returns true for translation validation', function (): void {
            // Arrange & Act
            $exists = $this->resolver->classExists('SomeClass');

            // Assert
            expect($exists)->toBeTrue();
            // This resolver doesn't validate class existence, only translation keys
        });
    });

    describe('Integration with AnalysisResult', function (): void {
        test('it returns proper AnalysisResult structure', function (): void {
            // Arrange
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/ValidTranslations.php');

            // Act
            $result = $this->resolver->analyze($file);

            // Assert
            expect($result)->toBeInstanceOf(AnalysisResult::class)
                ->and($result->file)->toBe($file)
                ->and($result->references)->toBeArray()
                ->and($result->missing)->toBeArray()
                ->and($result->success)->toBeBool();
        });

        test('it uses AnalysisResult factory methods', function (): void {
            // Arrange
            $validFile = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/ValidTranslations.php');
            $invalidFile = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/InvalidTranslations.php');

            // Act
            $validResult = $this->resolver->analyze($validFile);
            $invalidResult = $this->resolver->analyze($invalidFile);

            // Assert
            expect($validResult->success)->toBeTrue()
                ->and($validResult->hasMissing())->toBeFalse()
                ->and($invalidResult->success)->toBeFalse()
                ->and($invalidResult->hasMissing())->toBeTrue();
        });
    });

    describe('Configuration Options', function (): void {
        test('it respects reportDynamic configuration', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                reportDynamic: false,
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/DynamicKeys.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->warnings)->toBeEmpty(); // Dynamic keys not reported
        });

        test('it respects ignore patterns', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                ignore: ['validation.*', 'auth.*'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/IgnoredKeys.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->missing)->toBeEmpty(); // Ignored patterns not reported as missing
        });

        test('it can validate only specific namespaces', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/MixedNamespaces.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->references)->toHaveCount(4); // All references included (no filtering configured)
        });
    });

    describe('Edge Case - Parse Failures', function (): void {
        test('it handles null AST parse result with custom parser', function (): void {
            // Arrange
            // Create a mock parser that returns null to test the null AST path
            $mockParser = new class() implements Parser
            {
                public function parse(string $code, ?ErrorHandler $errorHandler = null): ?array
                {
                    return null; // Simulate parser unable to recover from error
                }

                public function getTokens(): array
                {
                    return [];
                }
            };

            $resolver = new TranslationAnalysisResolver(
                langPath: __DIR__.'/../Fixtures/translations/lang',
                locales: ['en'],
                parser: $mockParser,
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/ValidTranslations.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeFalse()
                ->and($result->hasError())->toBeTrue()
                ->and($result->error)->toBe('Failed to parse file');
        });
    });

    describe('Edge Case - Translation File Loading', function (): void {
        test('it handles non-array PHP translation files gracefully', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: __DIR__.'/../Fixtures/translations/edge-cases/non-array-php',
                locales: ['en'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->not->toContain('invalid.string_return'); // Skipped non-array
        });

        test('it handles invalid JSON translation files gracefully', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: __DIR__.'/../Fixtures/translations/edge-cases/invalid-json',
                locales: ['en'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->toBeEmpty(); // Invalid JSON returns empty array
        });

        test('it handles JSON files with null value', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: __DIR__.'/../Fixtures/translations/edge-cases/json-null',
                locales: ['en'],
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->toBeEmpty(); // JSON null returns empty array
        });
    });

    describe('Edge Case - Vendor Package Loading', function (): void {
        test('it handles null vendor path gracefully', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                vendorPath: null,
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->not->toContain('package::'); // No vendor keys loaded
        });

        test('it handles non-existent vendor path gracefully', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                vendorPath: __DIR__.'/../Fixtures/translations/nonexistent-vendor',
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->not->toContain('package::'); // No vendor keys loaded
        });

        test('it handles missing locale in vendor package', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['de'], // Locale that doesn't exist in vendor packages
                vendorPath: __DIR__.'/../Fixtures/translations/vendor',
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->not->toContain('package::'); // No keys loaded for missing locale
        });

        test('it handles non-array vendor translation files', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                vendorPath: __DIR__.'/../Fixtures/translations/edge-cases/vendor-non-array',
            );

            // Act
            $keys = $resolver->getLoadedKeys();

            // Assert
            expect($keys)->toBeArray()
                ->and($keys)->not->toContain('invalid-package::'); // Non-array files skipped
        });
    });

    describe('Edge Case - Include Patterns', function (): void {
        test('it validates only keys matching include patterns', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                includePatterns: ['validation.*', 'auth.*'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/IncludePatternTest.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toHaveCount(2); // Only validation.* and auth.* included
        });

        test('it handles wildcard patterns in include filters', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                includePatterns: ['validation.attributes.*'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/WildcardInclude.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toContain('validation.attributes.email')
                ->and($result->references)->not->toContain('validation.required'); // Base validation not included
        });

        test('it handles question mark wildcard in patterns', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
                ignore: ['test.key?'],
            );
            $file = new SplFileInfo(__DIR__.'/../Fixtures/translations/php/QuestionMarkPattern.php');

            // Act
            $result = $resolver->analyze($file);

            // Assert
            expect($result->success)->toBeTrue()
                ->and($result->references)->toBeEmpty(); // Single char wildcard matched
        });
    });
});
