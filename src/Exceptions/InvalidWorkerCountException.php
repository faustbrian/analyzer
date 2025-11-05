<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Exceptions;

use InvalidArgumentException;

use function sprintf;

/**
 * Exception thrown when worker count is invalid for parallel processing.
 *
 * This exception is raised when attempting to configure a ParallelProcessor
 * with an invalid worker count (less than 1), which would cause division by
 * zero during chunk distribution.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidWorkerCountException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for invalid worker count.
     *
     * Factory method that provides a descriptive error message including
     * the invalid worker count that was provided.
     *
     * @param  int  $workers The invalid worker count that was provided
     * @return self Exception instance with descriptive error message
     */
    public static function create(int $workers): self
    {
        return new self(sprintf('Workers must be at least 1, got %d.', $workers));
    }
}
