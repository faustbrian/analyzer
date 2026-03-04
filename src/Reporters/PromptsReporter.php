<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Reporters;

use Cline\Analyzer\Contracts\ReporterInterface;
use Cline\Analyzer\Data\AnalysisResult;
use SplFileInfo;

use function array_count_values;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function array_slice;
use function array_unique;
use function array_values;
use function arsort;
use function count;
use function explode;
use function implode;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;
use function sprintf;

/**
 * Terminal-based reporter using Laravel Prompts for formatted output.
 *
 * This reporter provides rich, colorful terminal output during analysis using
 * Laravel's Prompts package. It tracks progress counters, displays detailed
 * statistics about missing class references, and presents results in both
 * human-readable lists and tabular formats.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PromptsReporter implements ReporterInterface
{
    /**
     * Total number of files to analyze.
     */
    private int $totalFiles = 0;

    /**
     * Number of files analyzed so far.
     */
    private int $processedFiles = 0;

    /**
     * Number of files with missing class references.
     */
    private int $failedFiles = 0;

    /**
     * Number of files with all references resolved.
     */
    private int $successFiles = 0;

    /**
     * Number of files that crashed during analysis.
     */
    private int $errorFiles = 0;

    /**
     * Initialize analysis and display startup information.
     *
     * Resets all counters and displays the total number of files that will
     * be analyzed. This provides users with immediate feedback about the
     * scope of the analysis operation.
     *
     * @param array<SplFileInfo> $files Collection of files to be analyzed
     */
    public function start(array $files): void
    {
        $this->totalFiles = count($files);
        $this->processedFiles = 0;
        $this->failedFiles = 0;
        $this->successFiles = 0;
        $this->errorFiles = 0;

        info(sprintf('Analyzing %d files...', $this->totalFiles));
    }

    /**
     * Update progress counters for each analyzed file.
     *
     * Tracks both the total number of processed files and maintains separate
     * counters for successful and failed analyses. These counters are used
     * during the finish phase to display comprehensive statistics.
     *
     * @param AnalysisResult $result Analysis result for a single file
     */
    public function progress(AnalysisResult $result): void
    {
        ++$this->processedFiles;

        if ($result->hasError()) {
            ++$this->errorFiles;
        } elseif ($result->success) {
            ++$this->successFiles;
        } else {
            ++$this->failedFiles;
        }
    }

    /**
     * Display final analysis results with detailed statistics.
     *
     * Shows comprehensive summary including total files processed, success/failure
     * counts, and if failures exist, displays detailed breakdowns of missing classes
     * including top offenders and most affected namespaces.
     *
     * @param array<AnalysisResult> $results Complete collection of all analysis results
     */
    public function finish(array $results): void
    {
        note('');
        note(sprintf('Analysis complete: %d/%d files processed', $this->processedFiles, $this->totalFiles));
        note(sprintf('✓ %d passed', $this->successFiles));

        if ($this->failedFiles > 0) {
            note(sprintf('✗ %d failed', $this->failedFiles));
        }

        if ($this->errorFiles > 0) {
            note(sprintf('⚠ %d errors', $this->errorFiles));
        }

        if ($this->failedFiles > 0 || $this->errorFiles > 0) {
            note('');

            if ($this->failedFiles > 0) {
                $this->displaySummary($results);
                $this->displayFailures($results);
            }

            if ($this->errorFiles > 0) {
                $this->displayErrors($results);
            }
        } else {
            note('');
            info('All class references exist!');
        }
    }

    /**
     * Display aggregate statistics about missing class references.
     *
     * Analyzes all failed results to compute and display useful statistics including
     * total missing references, unique missing classes, top 5 most frequently missing
     * classes, and top 3 most affected namespaces. This helps identify patterns and
     * prioritize fixes by showing which dependencies or namespaces have the most issues.
     *
     * @param array<AnalysisResult> $results Collection of all analysis results
     */
    private function displaySummary(array $results): void
    {
        $failures = array_filter($results, fn (AnalysisResult $r): bool => !$r->success);

        $allMissing = array_merge(...array_map(
            fn (AnalysisResult $r): array => $r->missing,
            $failures,
        ));

        $totalMissing = count($allMissing);
        $uniqueMissing = count(array_unique($allMissing));

        /** @var array<string, int<1, max>> $missingCounts */
        $missingCounts = array_count_values($allMissing);
        arsort($missingCounts);

        $topBroken = array_slice($missingCounts, 0, 5, true);

        $namespaces = array_map(function (string $class): string {
            $parts = explode('\\', $class);
            array_pop($parts);

            return $parts !== [] ? implode('\\', $parts) : '(global)';
        }, array_keys($missingCounts));

        /** @var array<string, int<1, max>> $namespaceCounts */
        $namespaceCounts = array_count_values($namespaces);
        arsort($namespaceCounts);

        $topNamespaces = array_slice($namespaceCounts, 0, 3, true);

        info('Summary Statistics:');
        note('');
        note(sprintf('  Total missing references: %d', $totalMissing));
        note(sprintf('  Unique missing classes: %d', $uniqueMissing));
        note('');

        warning('Top 5 Most Referenced Missing Classes:');
        note('');

        foreach ($topBroken as $class => $count) {
            note(sprintf('  %dx  %s', $count, $class));
        }

        note('');
        warning('Top 3 Most Broken Namespaces:');
        note('');

        foreach ($topNamespaces as $namespace => $count) {
            note(sprintf('  %dx  %s', $count, $namespace));
        }

        note('');
    }

    /**
     * Display detailed failure information for each affected file.
     *
     * Shows a comprehensive list of all files that failed analysis along with
     * their specific missing class references. Also generates a tabular summary
     * view for easier scanning of file-to-class relationships. This provides
     * both detailed and overview perspectives of the failures.
     *
     * @param array<AnalysisResult> $results Collection of all analysis results
     */
    private function displayFailures(array $results): void
    {
        $failures = array_filter($results, fn (AnalysisResult $r): bool => !$r->success);

        if ($failures === []) {
            return; // @codeCoverageIgnore
        }

        error('Missing class references found:');
        note('');

        foreach ($failures as $result) {
            warning($result->file->getPathname());

            foreach ($result->missing as $missing) {
                note('  → '.$missing);
            }

            note('');
        }

        /** @var array<int, array{File: string, 'Missing Class': string}> $tableData */
        $tableData = [];

        foreach ($failures as $result) {
            foreach ($result->missing as $missing) {
                $tableData[] = [
                    'File' => $result->file->getFilename(),
                    'Missing Class' => $missing,
                ];
            }
        }

        if ($tableData === []) {
            return;
        }

        /** @var array<int, string> $headers */
        $headers = ['File', 'Missing Class'];

        /** @var array<int, array<int, string>> $rows */
        $rows = array_map(array_values(...), $tableData);
        table($headers, $rows);
    }

    /**
     * Display errors that occurred during analysis.
     *
     * Shows files that crashed during analysis with their error messages,
     * helping identify syntax errors, incompatible signatures, and other
     * fatal issues that prevented successful analysis.
     *
     * @param array<AnalysisResult> $results Collection of all analysis results
     */
    private function displayErrors(array $results): void
    {
        $errors = array_filter($results, fn (AnalysisResult $r): bool => $r->hasError());

        if ($errors === []) {
            return; // @codeCoverageIgnore
        }

        error('Files with analysis errors:');
        note('');

        foreach ($errors as $result) {
            warning($result->file->getPathname());
            note('  → '.$result->error);
            note('');
        }
    }
}
