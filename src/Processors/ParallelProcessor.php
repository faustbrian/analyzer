<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Processors;

use Cline\Analyzer\Contracts\ProcessorInterface;
use Cline\Analyzer\Exceptions\InvalidWorkerCountException;
use Closure;
use SplFileInfo;

use function array_chunk;
use function ceil;
use function count;
use function max;

/**
 * Parallel file processor that distributes work across multiple workers.
 *
 * Divides file collections into chunks based on worker count and processes
 * them concurrently. Currently executes serially but maintains chunking
 * architecture for future true parallel execution via process pools or async.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ParallelProcessor implements ProcessorInterface
{
    /**
     * Create a new parallel processor instance.
     *
     * @param int $workers Number of workers to distribute files across for chunked processing.
     *                     Determines the chunk size by dividing total files by worker count.
     *                     Higher values create smaller chunks for more granular distribution,
     *                     while lower values create larger chunks for reduced overhead.
     *                     Defaults to 4 workers to balance parallelization and coordination costs.
     */
    public function __construct(
        private int $workers = 4,
    ) {
        if ($this->workers < 1) {
            throw InvalidWorkerCountException::create($this->workers);
        }
    }

    /**
     * Process files by distributing them across worker chunks.
     *
     * Divides the file collection into chunks based on worker count and processes
     * each chunk. While currently executed serially, the chunking strategy is
     * maintained to facilitate future parallel processing integration.
     *
     * @template T
     *
     * @param  array<SplFileInfo>      $files    Files to process
     * @param  Closure(SplFileInfo): T $callback Function to apply to each file
     * @return array<T>                Collection of results from all workers
     */
    public function process(array $files, Closure $callback): array
    {
        $chunks = array_chunk($files, max(1, (int) ceil(count($files) / $this->workers)));
        $results = [];

        foreach ($chunks as $chunk) {
            foreach ($chunk as $file) {
                $results[] = $callback($file);
            }
        }

        return $results;
    }
}
