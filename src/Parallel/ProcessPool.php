<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Parallel;

use Closure;
use SplFileInfo;

use function array_chunk;
use function array_map;
use function ceil;
use function count;
use function max;

/**
 * Utility for processing files in parallel or serial execution mode.
 *
 * This class provides a simplified interface for distributing file analysis tasks
 * across multiple workers or processing them sequentially. The implementation currently
 * uses serial execution but maintains the worker-based chunking architecture to support
 * future parallel processing integration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ProcessPool
{
    /**
     * Process files by distributing them across worker chunks.
     *
     * Divides the file collection into chunks based on the worker count and applies
     * the callback to each file. While currently executed serially, this method maintains
     * the chunking strategy to facilitate future parallel processing with process pools
     * or async execution.
     *
     * @template T
     *
     * @param  array<SplFileInfo>      $files    Files to process
     * @param  Closure(SplFileInfo): T $callback Function to apply to each file, returning analysis results
     * @param  int                     $workers  Number of workers to distribute files across (default: 4)
     * @return array<T>                Collection of results from applying callback to each file
     */
    public static function map(array $files, Closure $callback, int $workers = 4): array
    {
        $chunks = array_chunk($files, max(1, (int) ceil(count($files) / $workers)));
        $results = [];

        foreach ($chunks as $chunk) {
            foreach ($chunk as $file) {
                $results[] = $callback($file);
            }
        }

        return $results;
    }

    /**
     * Process files sequentially without worker distribution.
     *
     * Applies the callback to each file in order without chunking or distribution.
     * This method is preferred for simpler use cases or when deterministic execution
     * order is required.
     *
     * @template T
     *
     * @param  array<SplFileInfo>      $files    Files to process
     * @param  Closure(SplFileInfo): T $callback Function to apply to each file
     * @return array<T>                Collection of results from applying callback to each file
     */
    public static function mapSerial(array $files, Closure $callback): array
    {
        return array_map($callback, $files);
    }
}
