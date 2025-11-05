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

use function array_all;
use function array_values;
use function fnmatch;
use function is_dir;
use function is_file;
use function iterator_to_array;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

/**
 * Resolves file system paths into analyzable PHP file collections.
 *
 * This resolver handles the conversion of mixed path inputs (individual files
 * or directories) into a flat collection of PHP files suitable for analysis.
 * It recursively traverses directories, filters for PHP files, and excludes
 * hidden files and specified patterns that should not be analyzed.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class FileResolver implements FileResolverInterface
{
    /**
     * Create a new file resolver instance.
     *
     * @param array<string> $excludePatterns Glob patterns for files and directories to exclude from scanning.
     *                                       Patterns support wildcards and match against both file names and
     *                                       directory paths. Common patterns include 'vendor', 'node_modules',
     *                                       'storage', or 'vendor/*' for glob-style matching. Empty array includes
     *                                       all PHP files except hidden files (starting with dot).
     */
    public function __construct(
        private array $excludePatterns = [],
    ) {}

    /**
     * Determine if a file should be included in analysis.
     *
     * Applies filtering rules to determine if a file is eligible for analysis.
     * Includes PHP files while excluding hidden files (those starting with a dot)
     * and files matching the configured exclude patterns. This prevents analysis
     * of system files, IDE metadata, vendor directories, and other excluded resources.
     *
     * @param  SplFileInfo $file File to evaluate
     * @return bool        True if file should be analyzed, false otherwise
     */
    public function shouldAnalyze(SplFileInfo $file): bool
    {
        if ($file->getExtension() !== 'php' || str_starts_with($file->getFilename(), '.')) {
            return false;
        }

        $path = $file->getPathname();

        return array_all($this->excludePatterns, fn (string $pattern): bool => !$this->matchesPattern($path, $pattern));
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

    /**
     * Check if a path matches an exclude pattern.
     *
     * Supports both glob patterns (with wildcards) and simple substring matching.
     * Path separators are normalized to forward slashes for cross-platform compatibility.
     * Patterns without wildcards perform substring matching (e.g., 'vendor' matches
     * '/path/to/vendor/file.php'). Patterns with wildcards (*,?) use fnmatch with
     * automatic wrapping for partial path matching.
     *
     * @param  string $path    File path to check against pattern
     * @param  string $pattern Glob pattern or substring to match against
     * @return bool   True if path matches pattern (should be excluded), false otherwise
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Normalize path separators
        $path = str_replace('\\', '/', $path);
        $pattern = str_replace('\\', '/', $pattern);

        // If pattern contains wildcards, use fnmatch
        if (str_contains($pattern, '*') || str_contains($pattern, '?')) {
            return fnmatch(sprintf('*%s*', $pattern), $path);
        }

        // Otherwise do simple substring match
        return str_contains($path, $pattern);
    }
}
