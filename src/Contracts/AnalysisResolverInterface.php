<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Contracts;

use Cline\Analyzer\Data\AnalysisResult;
use SplFileInfo;

/**
 * Contract for analyzing PHP files and validating class references.
 *
 * Defines the interface for extracting class references from PHP files and validating
 * their existence. Implementations should handle parsing source code, identifying all
 * class references (imports, type hints, PHPDoc annotations), and checking whether
 * referenced classes can be loaded or resolved.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface AnalysisResolverInterface
{
    /**
     * Analyze a single file for class references and validate their existence.
     *
     * Extracts all class references from the file including use statements, type hints,
     * and PHPDoc annotations, then validates each reference to determine if the class
     * exists or can be loaded. Returns a result object containing validation status and
     * any missing class references found.
     *
     * @param  SplFileInfo    $file File to analyze for class references
     * @return AnalysisResult Result object containing analysis status and missing references
     */
    public function analyze(SplFileInfo $file): AnalysisResult;

    /**
     * Check if a class reference exists and can be loaded.
     *
     * Validates whether a fully qualified class name represents a loadable class,
     * interface, or trait. Should return true if the class exists in the runtime
     * or can be autoloaded, false otherwise.
     *
     * @param  string $class Fully qualified class name to validate
     * @return bool   True if the class exists or can be loaded, false otherwise
     */
    public function classExists(string $class): bool;
}
