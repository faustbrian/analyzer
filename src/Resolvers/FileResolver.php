<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Resolvers;

use AppendIterator;
use ArrayIterator;
use CallbackFilterIterator;
use Cline\Analyzer\Contracts\FileResolverInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_values;
use function is_dir;
use function is_file;
use function iterator_to_array;
use function str_starts_with;

/**
 * Resolves file system paths into analyzable PHP file collections.
 *
 * This resolver handles the conversion of mixed path inputs (individual files
 * or directories) into a flat collection of PHP files suitable for analysis.
 * It recursively traverses directories, filters for PHP files, and excludes
 * hidden files that typically should not be analyzed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileResolver implements FileResolverInterface
{
    /**
     * Determine if a file should be included in analysis.
     *
     * Applies filtering rules to determine if a file is eligible for analysis.
     * Currently includes PHP files while excluding hidden files (those starting
     * with a dot). This prevents analysis of system files, IDE metadata, and
     * other typically hidden resources.
     *
     * @param  SplFileInfo $file File to evaluate
     * @return bool        True if file should be analyzed, false otherwise
     */
    public function shouldAnalyze(SplFileInfo $file): bool
    {
        return $file->getExtension() === 'php' && !str_starts_with($file->getFilename(), '.');
    }

    /**
     * Resolve paths into a collection of PHP files for analysis.
     *
     * Processes a mixed array of file and directory paths, recursively scanning
     * directories and collecting all eligible PHP files. Individual file paths are
     * included directly while directory paths are recursively traversed. The result
     * is a flat array of all PHP files found across all provided paths.
     *
     * @param  array<string>      $paths Array of file or directory paths to resolve
     * @return array<SplFileInfo> Flat collection of all PHP files found, with hidden files excluded
     */
    public function getFiles(array $paths): array
    {
        $iterator = new AppendIterator();

        foreach ($paths as $path) {
            if (is_file($path)) {
                $iterator->append(
                    new ArrayIterator([new SplFileInfo($path)]),
                );
            } elseif (is_dir($path)) {
                $iterator->append(
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($path),
                    ),
                );
            }
        }

        $files = new CallbackFilterIterator(
            $iterator,
            fn ($file): bool => $file instanceof SplFileInfo && !$file->isDir() && $this->shouldAnalyze($file),
        );

        /** @var array<SplFileInfo> */
        return array_values(iterator_to_array($files));
    }
}
