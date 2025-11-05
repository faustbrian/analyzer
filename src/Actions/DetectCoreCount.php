<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Actions;

use Illuminate\Support\Facades\Process;

use function max;
use function mb_trim;

/**
 * Detects the number of CPU cores available on the system.
 *
 * Uses platform-specific commands to determine CPU core count for optimizing
 * parallel processing tasks. Falls back to a safe default when detection fails.
 *
 * Supported platforms:
 * - macOS: Uses sysctl -n hw.ncpu
 * - Linux: Uses nproc command
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class DetectCoreCount
{
    /**
     * Execute the core count detection.
     *
     * Attempts to detect CPU cores using platform-specific commands with stderr
     * redirection to suppress error output. Returns minimum of 1 core to prevent
     * invalid configurations, and defaults to 4 cores if all detection methods fail.
     *
     * @return int Number of CPU cores detected (minimum 1, fallback 4 on detection failure)
     */
    public function __invoke(): int
    {
        $result = Process::run('sysctl -n hw.ncpu 2>/dev/null || nproc 2>/dev/null || echo 4');

        if ($result->failed()) {
            return 4;
        }

        $cores = (int) mb_trim($result->output());

        return max(1, $cores);
    }
}
