<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Processors;

use Cline\Analyzer\Contracts\ProcessorInterface;
use Closure;
use SplFileInfo;

use function array_map;

/**
 * Serial file processor that executes tasks sequentially.
 *
 * Processes files one at a time in order without parallelization or worker
 * distribution. Provides deterministic execution order and simplicity at
 * the cost of throughput on multi-core systems.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SerialProcessor implements ProcessorInterface
{
    /**
     * Process files sequentially in order.
     *
     * Applies the callback to each file one at a time, maintaining execution
     * order and collecting results into a single array.
     *
     * @template T
     *
     * @param  array<SplFileInfo>      $files    Files to process
     * @param  Closure(SplFileInfo): T $callback Function to apply to each file
     * @return array<T>                Collection of results in processing order
     */
    public function process(array $files, Closure $callback): array
    {
        return array_map($callback, $files);
    }
}
