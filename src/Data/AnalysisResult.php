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
     * @param SplFileInfo   $file       File that was analyzed
     * @param array<string> $references Complete list of all class references found in the file,
     *                                  including both valid and missing classes. Used for
     *                                  comprehensive reporting and statistics.
     * @param array<string> $missing    Subset of references that could not be resolved or loaded,
     *                                  indicating potentially broken imports, typos, or missing
     *                                  dependencies. Empty array indicates successful analysis.
     * @param bool          $success    Indicates whether the analysis passed without finding missing classes.
     *                                  True when no missing references were found, false otherwise.
     */
    public function __construct(
        public SplFileInfo $file,
        public array $references,
        public array $missing,
        public bool $success,
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
}
