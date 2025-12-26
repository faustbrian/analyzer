<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Data;

use SplFileInfo;

/**
 * Immutable data object representing the analysis result for a single PHP file.
 *
 * This value object encapsulates the outcome of analyzing a PHP file for class
 * references, including all discovered class references and any missing classes
 * that could not be resolved. The immutable nature ensures thread-safety when
 * processing files in parallel.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class AnalysisResult
{
    /**
     * Create a new analysis result.
     *
     * @param SplFileInfo                 $file       File that was analyzed
     * @param array<string>               $references Complete list of all class references found in the file,
     *                                                including both valid and missing classes. Used for
     *                                                comprehensive reporting and statistics.
     * @param array<string>               $missing    Subset of references that could not be resolved or loaded,
     *                                                indicating potentially broken imports, typos, or missing
     *                                                dependencies. Empty array indicates successful analysis.
     * @param bool                        $success    Indicates whether the analysis passed without finding missing classes.
     *                                                True when no missing references were found, false otherwise.
     * @param null|string                 $error      Error message if analysis crashed, null if analysis completed
     * @param array<array<string, mixed>> $warnings   Non-critical issues found during analysis such as dynamic
     *                                                array keys or ambiguous references that may require manual review
     */
    public function __construct(
        public SplFileInfo $file,
        public array $references,
        public array $missing,
        public bool $success,
        public ?string $error = null,
        public array $warnings = [],
    ) {}

    /**
     * Create a successful analysis result.
     *
     * Factory method for creating results when all class references in a file
     * were successfully resolved. This indicates the file has no broken imports
     * or missing dependencies.
     *
     * @param  SplFileInfo   $file       File that was analyzed
     * @param  array<string> $references All class references found in the file
     * @return self          Analysis result with success status and empty missing array
     */
    public static function success(SplFileInfo $file, array $references): self
    {
        return new self($file, $references, [], true);
    }

    /**
     * Create a failed analysis result.
     *
     * Factory method for creating results when one or more class references could
     * not be resolved. This indicates the file has broken imports, typos in class
     * names, or missing dependencies that need to be addressed.
     *
     * @param  SplFileInfo   $file       File that was analyzed
     * @param  array<string> $references All class references found in the file
     * @param  array<string> $missing    Subset of references that could not be resolved
     * @return self          Analysis result with failure status and populated missing array
     */
    public static function failure(SplFileInfo $file, array $references, array $missing): self
    {
        return new self($file, $references, $missing, false);
    }

    /**
     * Create an error analysis result.
     *
     * Factory method for creating results when file analysis crashed with an error.
     * This indicates a fatal error occurred during analysis (e.g., syntax errors,
     * incompatible method signatures) that prevented successful completion.
     *
     * @param  SplFileInfo $file  File that was being analyzed
     * @param  string      $error Error message describing what went wrong
     * @return self        Analysis result with error status
     */
    public static function error(SplFileInfo $file, string $error): self
    {
        return new self($file, [], [], false, $error);
    }

    /**
     * Check if the analysis found any missing class references.
     *
     * Convenience method to determine if the file has unresolved class references
     * without directly checking the missing array or success flag.
     *
     * @return bool True if missing references exist, false otherwise
     */
    public function hasMissing(): bool
    {
        return $this->missing !== [];
    }

    /**
     * Check if the analysis crashed with an error.
     *
     * @return bool True if analysis encountered a fatal error, false otherwise
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }
}
