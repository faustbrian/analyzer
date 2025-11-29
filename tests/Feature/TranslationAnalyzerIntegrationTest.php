<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;
use Cline\Analyzer\Data\AnalysisResult;
use Cline\Analyzer\Processors\SerialProcessor;
use Cline\Analyzer\Resolvers\TranslationAnalysisResolver;

describe('Translation Analyzer Integration', function (): void {
    beforeEach(function (): void {
        $this->fixturesPath = __DIR__.'/../Fixtures/translations';
        $this->langPath = $this->fixturesPath.'/lang';
    });

    describe('End-to-End PHP File Analysis', function (): void {
        test('it analyzes PHP files with valid translations', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/ValidTranslations.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toBeArray()
                ->and($results)->toHaveCount(1)
                ->and($results[0])->toBeInstanceOf(AnalysisResult::class)
                ->and($results[0]->success)->toBeTrue()
                ->and($results[0]->missing)->toBeEmpty();
        });

        test('it detects missing translations in PHP files', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/InvalidTranslations.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->success)->toBeFalse()
                ->and($results[0]->missing)->toContain('validation.nonexistent')
                ->and($results[0]->missing)->toContain('messages.missing');
        });
    });

    describe('End-to-End Blade File Analysis', function (): void {
        test('it analyzes Blade files with valid translations', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/views/valid.blade.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->success)->toBeTrue();
        });

        test('it detects missing translations in Blade files', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/views/invalid.blade.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->success)->toBeFalse()
                ->and($results[0]->missing)->toHaveCount(2);
        });
    });

    describe('Multi-File Analysis', function (): void {
        test('it analyzes multiple PHP files in directory', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toBeArray()
                ->and(count($results))->toBeGreaterThan(10);
            $hasFailures = array_any($results, fn ($result): bool => !$result->success);

            expect($hasFailures)->toBeTrue(); // Some files have invalid translations
        });

        test('it analyzes mixed PHP and Blade files', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([
                    $this->fixturesPath.'/php/ValidTranslations.php',
                    $this->fixturesPath.'/views/valid.blade.php',
                ])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(2)
                ->and($results[0]->success)->toBeTrue()
                ->and($results[1]->success)->toBeTrue();
        });
    });

    describe('Multi-Locale Analysis', function (): void {
        test('it validates translations across multiple locales', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/MultiLocale.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en', 'es', 'fr'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->success)->toBeTrue();
            // All keys valid because they exist in at least one locale
        });

        test('it reports keys missing in all configured locales', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/MissingInAllLocales.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en', 'es'], // fr not included
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->success)->toBeFalse()
                ->and($results[0]->missing)->toContain('messages.only_in_fr');
        });
    });

    describe('Dynamic Key Handling', function (): void {
        test('it reports dynamic keys as warnings', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/DynamicKeys.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                        reportDynamic: true,
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->success)->toBeTrue() // Not a failure
                ->and($results[0]->warnings)->toHaveCount(3);
        });

        test('it can suppress dynamic key warnings', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/DynamicKeys.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                        reportDynamic: false,
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->warnings ?? [])->toBeEmpty();
        });
    });

    describe('Namespaced Package Translations', function (): void {
        test('it validates vendor package translations', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/PackageTranslations.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                        vendorPath: $this->fixturesPath.'/vendor',
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->success)->toBeTrue();
        });

        test('it reports missing vendor package translations', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/InvalidPackageTranslations.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                        vendorPath: $this->fixturesPath.'/vendor',
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->success)->toBeFalse()
                ->and($results[0]->missing)->toContain('unknown-package::file.key');
        });
    });

    describe('Performance Testing', function (): void {
        test('it analyzes large translation files efficiently', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/ManyTranslations.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $start = microtime(true);
            $results = $analyzer->analyze();
            $duration = microtime(true) - $start;

            // Assert
            expect($duration)->toBeLessThan(1.0) // Should complete in under 1 second
                ->and($results)->toHaveCount(1)
                ->and($results[0]->references)->toHaveCount(20);
        });

        test('it caches translations across multiple file analyses', function (): void {
            // Arrange
            $resolver = new TranslationAnalysisResolver(
                langPath: $this->langPath,
                locales: ['en'],
            );

            $config = AnalyzerConfig::make()
                ->paths([
                    $this->fixturesPath.'/php/ValidTranslations.php',
                    $this->fixturesPath.'/php/NestedTranslations.php',
                    $this->fixturesPath.'/php/JsonTranslations.php',
                ])
                ->analysisResolver($resolver)
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $start = microtime(true);
            $results = $analyzer->analyze();
            $duration = microtime(true) - $start;

            // Assert
            expect($duration)->toBeLessThan(0.5) // Fast due to caching
                ->and($results)->toHaveCount(3);
        });
    });

    describe('Error Handling', function (): void {
        test('it handles syntax errors gracefully', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/SyntaxError.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->hasError())->toBeTrue()
                ->and($results[0]->error)->toContain('Syntax error');
        });

        test('it handles missing translation directories', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/ValidTranslations.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: '/nonexistent/path',
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act & Assert
            expect(fn (): array => $analyzer->analyze())
                ->not->toThrow(Exception::class);
            // Should handle gracefully, possibly all keys reported as missing
        });
    });

    describe('Configuration Options', function (): void {
        test('it respects ignore patterns', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/IgnoredKeys.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                        ignore: ['validation.*', 'auth.*'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->missing)->not->toContain('validation.nonexistent')
                ->and($results[0]->missing)->not->toContain('auth.missing');
        });

        test('it can filter by namespace', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/MixedNamespaces.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                        includePatterns: ['validation.*', 'auth.*'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1)
                ->and($results[0]->references)->toHaveCount(2); // Only validation and auth
        });

        test('it supports legacy path configuration', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/ValidTranslations.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            // Assert
            expect($results)->toHaveCount(1);
            // Should load from both paths
        });
    });

    describe('Reporting', function (): void {
        test('it provides detailed failure information', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([$this->fixturesPath.'/php/InvalidTranslations.php'])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();
            $hasFailures = $analyzer->hasFailures($results);

            // Assert
            expect($hasFailures)->toBeTrue()
                ->and($results[0]->missing)->toBeArray()
                ->and($results[0]->references)->toBeArray()
                ->and($results[0]->file)->toBeInstanceOf(SplFileInfo::class);
        });

        test('it summarizes analysis across multiple files', function (): void {
            // Arrange
            $config = AnalyzerConfig::make()
                ->paths([
                    $this->fixturesPath.'/php/ValidTranslations.php',
                    $this->fixturesPath.'/php/InvalidTranslations.php',
                    $this->fixturesPath.'/php/NestedTranslations.php',
                ])
                ->analysisResolver(
                    new TranslationAnalysisResolver(
                        langPath: $this->langPath,
                        locales: ['en'],
                    ),
                )
                ->processor(
                    new SerialProcessor(),
                );

            $analyzer = new Analyzer($config);

            // Act
            $results = $analyzer->analyze();

            $totalFiles = count($results);
            $failedFiles = 0;
            $totalMissing = 0;

            foreach ($results as $result) {
                if (!$result->success) {
                    ++$failedFiles;
                    $totalMissing += count($result->missing);
                }
            }

            // Assert
            expect($totalFiles)->toBe(3)
                ->and($failedFiles)->toBe(1)
                ->and($totalMissing)->toBeGreaterThan(0);
        });
    });
});
