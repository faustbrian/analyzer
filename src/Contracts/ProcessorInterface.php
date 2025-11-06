<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Contracts;

use Closure;
use SplFileInfo;

/**
 * Contract for file processing strategies.
 *
 * Defines the interface for different execution strategies (serial, parallel)
 * when processing collections of files. Implementations determine how files
 * are distributed and executed across workers or processed sequentially.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ProcessorInterface
{
    /**
     * Process a collection of files using the implementation's strategy.
     *
     * Applies the callback to each file and returns the collected results.
     * The execution strategy (serial, parallel, distributed) is determined
     * by the concrete implementation.
     *
     * @template T
     *
     * @param  array<SplFileInfo>      $files    Files to process
     * @param  Closure(SplFileInfo): T $callback Function to apply to each file
     * @return array<T>                Collection of results from processing all files
     */
    public function process(array $files, Closure $callback): array;
}
