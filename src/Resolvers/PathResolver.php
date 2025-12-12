<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Resolvers;

use Cline\Analyzer\Contracts\PathResolverInterface;

use function array_filter;
use function is_dir;
use function is_file;

/**
 * Validates and filters file system paths for analysis.
 *
 * This resolver ensures that only valid, existing paths are passed forward
 * to the file resolution layer. It filters out non-existent paths, broken
 * symlinks, and other invalid inputs that would cause errors during file
 * discovery and analysis.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PathResolver implements PathResolverInterface
{
    /**
     * Filters paths to only those that exist on the file system.
     *
     * Validates each path by checking if it exists as either a regular file
     * or directory. Non-existent paths, broken symlinks, and invalid inputs
     * are silently filtered out, ensuring only valid paths proceed to analysis.
     *
     * @param  array<string> $paths Paths to validate (may be files, directories, or invalid)
     * @return array<string> Filtered array containing only existing files and directories
     */
    public function resolve(array $paths): array
    {
        return array_filter($paths, fn (string $path): bool => is_dir($path) || is_file($path));
    }
}
