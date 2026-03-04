<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when attempting to analyze an empty class name.
 *
 * This exception is raised when the class inspector receives an empty string
 * or whitespace-only value where a valid fully-qualified class name is required.
 * It serves as a guard against invalid input early in the analysis process.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class EmptyClassNameException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for empty class name validation failures.
     *
     * Factory method that provides a consistent error message for cases where
     * an empty or whitespace-only class name is provided to the analyzer.
     *
     * @return self Exception instance with descriptive error message
     */
    public static function create(): self
    {
        return new self('The class name must be non-empty.');
    }
}
