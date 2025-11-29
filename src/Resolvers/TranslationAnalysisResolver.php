<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Resolvers;

use Cline\Analyzer\Analysis\BladeParser;
use Cline\Analyzer\Analysis\TranslationCallVisitor;
use Cline\Analyzer\Contracts\AnalysisResolverInterface;
use Cline\Analyzer\Data\AnalysisResult;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SplFileInfo;

use const GLOB_ONLYDIR;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_map;
use function basename;
use function dirname;
use function file_exists;
use function file_get_contents;
use function glob;
use function is_array;
use function is_dir;
use function json_decode;
use function preg_match;
use function preg_quote;
use function str_replace;

/**
 * Analyzes files for translation key references and validates their existence.
 *
 * Validates trans(), __(), and Lang::get() calls against actual translation files.
 * Loads translation keys from Laravel's lang directory, handles multiple locales,
 * supports both PHP and JSON translation files, and reports missing or invalid keys.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TranslationAnalysisResolver implements AnalysisResolverInterface
{
    /**
     * PHP parser instance for parsing PHP and Blade files.
     */
    private Parser $parser;

    /**
     * Loaded translation keys mapped to their existence.
     *
     * Keys are translation keys in dot notation (e.g., "validation.required"),
     * values are true for all existing translations.
     *
     * @var array<string, bool>
     */
    private array $translationKeys;

    /**
     * Creates a new translation analysis resolver.
     *
     * @param string             $langPath        Path to Laravel lang directory containing translation files.
     *                                            Typically located at lang/ or resources/lang/ in Laravel
     *                                            projects. Used as the base directory for scanning locale
     *                                            subdirectories and JSON translation files.
     * @param array<string>      $locales         List of locale codes to validate against. Defaults to ['en'].
     *                                            Each locale should correspond to a subdirectory in langPath
     *                                            (e.g., lang/en/) or a JSON file (e.g., lang/en.json).
     *                                            Multiple locales allow validation across all supported
     *                                            languages in the application.
     * @param bool               $reportDynamic   Report dynamic translation keys as warnings in analysis results.
     *                                            When true, trans() calls with variables or expressions trigger
     *                                            warnings since they cannot be statically validated against
     *                                            translation files.
     * @param null|string        $vendorPath      Path to vendor package translations directory for loading
     *                                            namespaced translations from packages (e.g., package::key).
     *                                            When specified, scans for package-specific translation files
     *                                            in vendor directories following Laravel's package structure.
     * @param array<string>      $ignore          Translation key glob patterns to ignore during validation.
     *                                            Keys matching any pattern are skipped even if they don't exist.
     *                                            Supports wildcards (* for any characters, ? for single char).
     *                                            Example: ['debug.*', 'temp.*'] to ignore debug and temp keys.
     * @param null|array<string> $includePatterns Only validate translation keys matching these glob patterns.
     *                                            When specified, keys not matching any pattern are ignored
     *                                            during validation. Useful for focusing validation on specific
     *                                            translation namespaces or groups. Supports wildcards.
     * @param null|Parser        $parser          Optional PHP parser instance for testing or custom parser
     *                                            configuration. When null, creates a parser for the newest
     *                                            supported PHP version automatically.
     * @param null|BladeParser   $bladeParser     Optional Blade template parser for converting Blade syntax
     *                                            to PHP before analysis. When null, Blade files are skipped.
     *                                            Defaults to a new BladeParser instance.
     */
    public function __construct(
        private string $langPath,
        private array $locales = ['en'],
        private bool $reportDynamic = true,
        private ?string $vendorPath = null,
        private array $ignore = [],
        private ?array $includePatterns = null,
        ?Parser $parser = null,
        private ?BladeParser $bladeParser = new BladeParser(),
    ) {
        $this->parser = $parser ?? new ParserFactory()->createForNewestSupportedVersion();
        $this->translationKeys = $this->loadTranslations();
    }

    /**
     * Analyzes a file for translation key references and validates their existence.
     *
     * Parses PHP and Blade files to discover all trans(), __(), and Lang::get() calls,
     * validates translation keys against loaded translation files, and reports missing
     * keys. Dynamic keys (using variables or expressions) are flagged as warnings when
     * reportDynamic is enabled.
     *
     * @param  SplFileInfo    $file File to analyze (PHP or Blade template)
     * @return AnalysisResult Analysis result containing discovered translation keys,
     *                        any missing keys, and warnings for dynamic keys or missing vendor packages
     */
    public function analyze(SplFileInfo $file): AnalysisResult
    {
        $path = $file->getRealPath();
        $content = (string) file_get_contents($path);

        // Parse Blade templates to PHP first
        if (BladeParser::isBladeFile($path) && $this->bladeParser instanceof BladeParser) {
            $content = $this->bladeParser->parse($content);
        }

        try {
            $ast = $this->parser->parse($content);
        } catch (Error $error) {
            return AnalysisResult::error($file, $error->getMessage());
        }

        if ($ast === null) {
            return AnalysisResult::error($file, 'Failed to parse file');
        }

        $traverser = new NodeTraverser();
        $visitor = new TranslationCallVisitor();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $translations = $visitor->getTranslationCalls() ?? [];

        // Find missing keys and warnings (filter first to build correct references)
        $missing = [];
        $warnings = [];
        $validatedTranslations = [];

        foreach ($translations as $translation) {
            if ($translation['dynamic']) {
                if ($this->reportDynamic) {
                    $warnings[] = [
                        'type' => 'dynamic_key',
                        'line' => $translation['line'],
                        'reason' => $translation['reason'] ?? 'dynamic',
                    ];
                }

                continue;
            }

            $key = $translation['key'];

            // Skip ignored/filtered keys
            if ($key !== null && $this->shouldIgnoreKey($key)) {
                continue;
            }

            // Add to validated translations for references
            $validatedTranslations[] = $translation;

            // Check for empty keys
            if ($key === '' || $translation['empty']) {
                $missing[] = $key;
                $warnings[] = [
                    'type' => 'empty_key',
                    'message' => 'Translation key is empty',
                ];

                continue;
            }

            // Check for missing vendor packages
            if ($translation['namespaced'] && $translation['package'] !== null && !$this->vendorPackageExists($translation['package'])) {
                $warnings[] = [
                    'type' => 'missing_vendor_package',
                    'package' => $translation['package'],
                ];
            }

            if ($key !== null && !$this->translationExists($key)) {
                $missing[] = $key;
            }
        }

        // Build references from validated translations
        $references = array_map(
            fn (array $t): string => $t['key'] ?? '',
            $validatedTranslations,
        );

        // Filter out null values from missing array
        $missing = array_map(fn (?string $m): string => (string) $m, $missing);

        if ($missing === []) {
            return new AnalysisResult($file, $references, [], true, null, $warnings);
        }

        return new AnalysisResult($file, $references, $missing, false, null, $warnings);
    }

    /**
     * Checks if a translation key exists in the loaded translations.
     *
     * @param  string $key Translation key in dot notation (e.g., 'validation.required')
     * @return bool   True if the key exists in any configured locale
     */
    public function translationExists(string $key): bool
    {
        return array_key_exists($key, $this->translationKeys);
    }

    /**
     * Gets all loaded translation keys.
     *
     * @return array<string> List of all translation keys loaded from translation files
     */
    public function getLoadedKeys(): array
    {
        return array_keys($this->translationKeys);
    }

    /**
     * Checks if a class exists (required by interface but not used for translations).
     *
     * @param  string $class Class name to check
     * @return bool   Always returns true as class checking is not applicable to translation analysis
     */
    public function classExists(string $class): bool
    {
        return true;
    }

    /**
     * Loads all translation keys from configured locales and vendor packages.
     *
     * Loads translations from multiple sources:
     * 1. PHP translation files in locale subdirectories (lang/en/*.php)
     * 2. JSON translation files (lang/en.json)
     * 3. Legacy resources/lang directory if it exists
     * 4. Vendor package translations if vendorPath is configured
     *
     * @return array<string, bool> Map of all discovered translation keys (all values true)
     */
    private function loadTranslations(): array
    {
        $keys = [];

        foreach ($this->locales as $locale) {
            // Load PHP translation files
            $localePath = $this->langPath.'/'.$locale;

            if (is_dir($localePath)) {
                $keys = [...$keys, ...$this->loadPhpTranslations($localePath)];
            }

            // Load JSON translation file
            $jsonPath = $this->langPath.'/'.$locale.'.json';

            if (file_exists($jsonPath)) {
                $keys = [...$keys, ...$this->loadJsonTranslations($jsonPath)];
            }

            // Load legacy resources/lang path if exists
            $legacyPath = dirname($this->langPath).'/resources/lang/'.$locale;

            if (is_dir($legacyPath)) {
                $keys = [...$keys, ...$this->loadPhpTranslations($legacyPath)];
            }

            // Load vendor package translations
            if ($this->vendorPath !== null) {
                $keys = [...$keys, ...$this->loadVendorTranslations($locale)];
            }
        }

        return $keys;
    }

    /**
     * Loads PHP translation files for a locale directory.
     *
     * Scans the locale directory for PHP files containing translation arrays,
     * loads each file, and flattens nested arrays into dot-notation keys
     * prefixed with the file's group name.
     *
     * @param  string              $path Absolute path to locale directory (e.g., lang/en/)
     * @return array<string, bool> Map of flattened translation keys from all PHP files
     */
    private function loadPhpTranslations(string $path): array
    {
        $keys = [];
        $files = glob($path.'/*.php') ?: [];

        foreach ($files as $file) {
            $group = basename($file, '.php');
            $translations = require $file;

            if (!is_array($translations)) {
                continue;
            }

            $keys = [...$keys, ...$this->flattenTranslations($translations, $group)];
        }

        return $keys;
    }

    /**
     * Loads JSON translation file for single-line translations.
     *
     * JSON translation files contain key-value pairs for translations that don't
     * need grouping. Keys are used directly as translation keys without prefixes.
     *
     * @param  string              $path Absolute path to JSON translation file (e.g., lang/en.json)
     * @return array<string, bool> Map of translation keys from the JSON file
     */
    private function loadJsonTranslations(string $path): array
    {
        $json = file_get_contents($path);
        $translations = json_decode($json ?: '', true);

        if (!is_array($translations)) {
            return [];
        }

        /** @var array<string, bool> */
        return array_fill_keys(array_keys($translations), true);
    }

    /**
     * Flattens nested translation array into dot notation keys.
     *
     * Recursively traverses nested arrays and converts them into flat keys
     * using dot notation. For example, ['user' => ['name' => '...']] becomes
     * 'group.user.name' when prefix is 'group'.
     *
     * @param  array<mixed>        $translations Translation array to flatten (can be nested)
     * @param  string              $prefix       Key prefix for current level (typically group name)
     * @return array<string, bool> Flattened keys in dot notation
     */
    private function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $keys = [];

        foreach ($translations as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = [...$keys, ...$this->flattenTranslations($value, $fullKey)];
            } else {
                $keys[$fullKey] = true;
            }
        }

        return $keys;
    }

    /**
     * Loads vendor package translations for a locale.
     *
     * Scans the vendor directory for package translation files and loads them
     * with package namespace prefixes (e.g., 'package::group.key'). This supports
     * Laravel's package translation feature where packages can publish translations
     * that are accessed using the package::key syntax.
     *
     * @param  string              $locale Locale code to load vendor translations for
     * @return array<string, bool> Map of namespaced translation keys (e.g., 'package::validation.required')
     */
    private function loadVendorTranslations(string $locale): array
    {
        $keys = [];

        if ($this->vendorPath === null || !is_dir($this->vendorPath)) {
            return $keys;
        }

        $packages = glob($this->vendorPath.'/*', GLOB_ONLYDIR) ?: [];

        foreach ($packages as $packagePath) {
            $packageName = basename($packagePath);
            $localePath = $packagePath.'/lang/'.$locale;

            if (!is_dir($localePath)) {
                continue;
            }

            $files = glob($localePath.'/*.php') ?: [];

            foreach ($files as $file) {
                $group = basename($file, '.php');
                $translations = require $file;

                if (!is_array($translations)) {
                    continue;
                }

                // Namespace with package name
                $keys = [...$keys, ...$this->flattenTranslations($translations, $packageName.'::'.$group)];
            }
        }

        return $keys;
    }

    /**
     * Checks if a translation key should be ignored based on configured patterns.
     *
     * Applies both include and ignore pattern filtering. When include patterns
     * are specified, keys must match at least one include pattern. Keys matching
     * any ignore pattern are always excluded. Patterns support glob wildcards
     * (* for any characters, ? for single character).
     *
     * @param  string $key Translation key in dot notation to check against patterns
     * @return bool   True if key should be ignored (excluded from validation)
     */
    private function shouldIgnoreKey(string $key): bool
    {
        // If include patterns specified, only include matching keys
        if ($this->includePatterns !== null) {
            $included = false;

            foreach ($this->includePatterns as $pattern) {
                $regex = preg_quote($pattern, '/');
                $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);
                $regex = '/^'.$regex.'$/';

                if (preg_match($regex, $key)) {
                    $included = true;

                    break;
                }
            }

            if (!$included) {
                return true;
            }
        }

        // Check ignore patterns
        foreach ($this->ignore as $pattern) {
            // Convert glob pattern to regex
            // Escape special regex characters except * and ?
            $regex = preg_quote($pattern, '/');
            // Convert glob wildcards to regex
            $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);
            $regex = '/^'.$regex.'$/';

            if (preg_match($regex, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a vendor package translation directory exists.
     *
     * Validates that the vendor path contains a package directory with a lang/
     * subdirectory, indicating the package has publishable translations available.
     *
     * @param  string $package Package name (e.g., 'laravel-permission')
     * @return bool   True if the package's translation directory exists at vendorPath/package/lang/
     */
    private function vendorPackageExists(string $package): bool
    {
        if ($this->vendorPath === null) {
            return false;
        }

        $packagePath = $this->vendorPath.'/'.$package.'/lang';

        return is_dir($packagePath);
    }
}
