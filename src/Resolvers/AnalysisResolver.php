<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Resolvers;

use Cline\Analyzer\Analysis\ClassInspector;
use Cline\Analyzer\Analysis\ReferenceAnalyzer;
use Cline\Analyzer\Contracts\AnalysisResolverInterface;
use Cline\Analyzer\Data\AnalysisResult;
use SplFileInfo;

use function preg_match;
use function str_replace;

/**
 * Resolves and validates class references within PHP files.
 *
 * This resolver analyzes PHP files to discover all class references and determines
 * which classes exist and which are missing. It supports glob-style ignore patterns
 * to exclude certain classes from validation, useful for ignoring generated code,
 * stubs, or intentionally missing development dependencies.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class AnalysisResolver implements AnalysisResolverInterface
{
    /**
     * Analyzer for discovering class references in files.
     *
     * This analyzer extracts all class references from PHP source code including
     * imported classes, type hints, instantiations, and static references.
     */
    private ReferenceAnalyzer $analyzer;

    /**
     * Create a new analysis resolver with optional ignore patterns.
     *
     * @param array<string> $ignore Glob-style patterns for class names to ignore during validation.
     *                              Supports wildcards (* for any characters, ? for single character).
     *                              Example: ['App\\Generated\\*', 'Tests\\Stubs\\*'] to ignore
     *                              all generated classes and test stubs.
     */
    public function __construct(
        private array $ignore = [],
    ) {
        $this->analyzer = new ReferenceAnalyzer();
    }

    /**
     * Analyze a file and validate all discovered class references.
     *
     * Extracts all class references from the file, validates their existence,
     * and returns a comprehensive result indicating success or failure. Classes
     * matching ignore patterns are excluded from validation.
     *
     * @param  SplFileInfo    $file PHP file to analyze
     * @return AnalysisResult Result containing all references and any missing classes
     */
    public function analyze(SplFileInfo $file): AnalysisResult
    {
        $path = $file->getRealPath();
        $references = $this->analyzer->analyze($path);
        $missing = [];

        foreach ($references as $class) {
            if ($this->shouldIgnore($class)) {
                continue;
            }

            if (!$this->classExists($class)) {
                $missing[] = $class;
            }
        }

        return $missing !== []
            ? AnalysisResult::failure($file, $references, $missing)
            : AnalysisResult::success($file, $references);
    }

    /**
     * Check if a class exists and can be loaded.
     *
     * Uses the ClassInspector to determine if a class is loadable through
     * autoloading or is already defined. This does not actually load the class
     * but verifies it can be loaded.
     *
     * @param  string $class Fully-qualified class name
     * @return bool   True if class exists or can be autoloaded, false otherwise
     */
    public function classExists(string $class): bool
    {
        return ClassInspector::inspect($class)->exists();
    }

    /**
     * Determine if a class should be ignored based on configured patterns.
     *
     * Matches the class name against all ignore patterns using glob-style matching
     * converted to regular expressions. Returns true if any pattern matches.
     *
     * @param  string $class Fully-qualified class name to check
     * @return bool   True if class matches any ignore pattern, false otherwise
     */
    private function shouldIgnore(string $class): bool
    {
        foreach ($this->ignore as $pattern) {
            $regex = str_replace(
                ['\\', '*', '?'],
                ['\\\\', '.*', '.'],
                $pattern,
            );
            $regex = '/^'.str_replace('\\\\', '\\\\', $regex).'$/';

            if (preg_match($regex, $class)) {
                return true;
            }
        }

        return false;
    }
}
