<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Contracts;

use Cline\Analyzer\Data\AnalysisResult;
use SplFileInfo;

/**
 * Contract for reporting analysis progress and results to users.
 *
 * Implementations of this interface handle the presentation of analysis lifecycle
 * events including initialization, progress updates, and completion summaries.
 * Different implementations can provide varied output formats such as terminal
 * prompts, JSON output, or silent operation for CI/CD environments.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ReporterInterface
{
    /**
     * Report the start of the analysis process.
     *
     * Called once at the beginning of analysis to initialize reporting state
     * and display information about the files that will be analyzed. This allows
     * reporters to set up counters, display headers, or perform other initialization.
     *
     * @param array<SplFileInfo> $files Collection of files that will be analyzed
     */
    public function start(array $files): void;

    /**
     * Report progress for a single analyzed file.
     *
     * Called after each file is analyzed to provide real-time feedback on the
     * analysis process. Reporters can update progress indicators, display errors,
     * or track success/failure counts based on the result.
     *
     * @param AnalysisResult $result The analysis result for a single file
     */
    public function progress(AnalysisResult $result): void;

    /**
     * Report the completion of the analysis process.
     *
     * Called once after all files have been analyzed to display final summaries,
     * statistics, and aggregate results. This is where reporters typically show
     * overall success/failure counts, detailed error reports, and exit status.
     *
     * @param array<AnalysisResult> $results Complete collection of all analysis results
     */
    public function finish(array $results): void;
}
