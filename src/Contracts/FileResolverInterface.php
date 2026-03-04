<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Contracts;

use SplFileInfo;

/**
 * Contract for discovering and filtering PHP files for analysis.
 *
 * Defines the interface for resolving file paths into collections of analyzable PHP files.
 * Implementations should handle recursive directory traversal, file filtering based on
 * extension and criteria, and determining which files should be included in analysis.
 * Used by the analyzer to discover files from configured paths.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface FileResolverInterface
{
    /**
     * Determine if the given file should be analyzed.
     *
     * Evaluates whether a file meets the criteria for analysis. Implementations should
     * check file extension, file type, and any other relevant criteria such as file
     * size, permissions, or naming patterns. Typically returns true for .php files
     * that are readable and not in excluded directories.
     *
     * @param  SplFileInfo $file File to evaluate for analysis eligibility
     * @return bool        True if the file should be analyzed, false to skip it
     */
    public function shouldAnalyze(SplFileInfo $file): bool;

    /**
     * Get all files to analyze from the given paths.
     *
     * Discovers and filters PHP files from the provided paths. Should handle both
     * individual file paths and directory paths, recursively traversing directories
     * to find all PHP files. Applies filtering logic via shouldAnalyze() to determine
     * which files to include in the result.
     *
     * @param  array<string>      $paths File or directory paths to scan for PHP files
     * @return array<SplFileInfo> Collection of file objects representing files to analyze
     */
    public function getFiles(array $paths): array;
}
