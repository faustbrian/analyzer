<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Contracts;

/**
 * Contract for resolving and validating file system paths before analysis.
 *
 * Implementations of this interface are responsible for filtering and validating
 * a collection of file or directory paths to ensure they exist and are accessible
 * before analysis operations are performed. This serves as a validation layer
 * between user input and the file resolution system.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface PathResolverInterface
{
    /**
     * Resolve and validate paths to analyze.
     *
     * Filters the provided paths to ensure they exist and are accessible
     * on the file system. Invalid or non-existent paths are removed from
     * the returned array, ensuring only valid paths proceed to analysis.
     *
     * @param  array<string> $paths Array of file or directory paths to validate
     * @return array<string> Filtered array containing only valid, existing paths
     */
    public function resolve(array $paths): array;
}
