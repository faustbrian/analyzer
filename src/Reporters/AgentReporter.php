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

use const SORT_REGULAR;

use function array_filter;
use function array_map;
use function array_merge;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function min;
use function sprintf;

/**
 * Agent-optimized reporter that generates XML-structured prompts for parallel agent execution.
 *
 * Outputs prompts using XML tags as recommended by Anthropic's prompt engineering guide.
 * Groups problems by namespace and generates specific fix instructions optimized for
 * spawning multiple parallel agents to maximize throughput when fixing large codebases.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AgentReporter implements ReporterInterface
{
    /**
     * Initialize the reporting process.
     *
     * This reporter produces no output during the start phase to keep the console
     * clean. All agent orchestration instructions are generated after analysis completes.
     *
     * @param array<SplFileInfo> $files Files that will be analyzed for missing class references
     */
    public function start(array $files): void
    {
        // Silent start - agent output only on finish
    }

    /**
     * Report progress for a single file analysis.
     *
     * This reporter produces no output during progress reporting to avoid cluttering
     * the console with intermediate results. The final orchestration output provides
     * all necessary information for parallel agent execution.
     *
     * @param AnalysisResult $result Analysis outcome for a single file, including any missing class references
     */
    public function progress(AnalysisResult $result): void
    {
        // Silent progress - agent output only on finish
    }

    /**
     * Generate final orchestration report with agent execution instructions.
     *
     * Analyzes all analysis results and generates XML-structured output when failures
     * are detected. Groups failures by namespace and creates parallelizable task
     * definitions optimized for spawning multiple concurrent agents.
     *
     * @param array<AnalysisResult> $results Complete collection of analysis results from all scanned files
     */
    public function finish(array $results): void
    {
        $failures = array_filter($results, fn (AnalysisResult $r): bool => !$r->success);

        if ($failures === []) {
            echo "✓ All class references exist - no fixes needed.\n";

            return;
        }

        $this->generateAgentPrompt($failures);
    }

    /**
     * Generate XML-structured agent orchestration prompt for parallel execution.
     *
     * Creates comprehensive agent orchestration instructions following Anthropic's
     * XML prompt engineering guidelines. Organizes failures by namespace to maximize
     * parallelization opportunities and includes both parallel and sequential execution
     * strategies. Caps agent count at 4 to balance parallelism with coordination overhead.
     *
     * @param array<AnalysisResult> $failures Analysis results that contain missing class references requiring fixes
     */
    private function generateAgentPrompt(array $failures): void
    {
        $groupedByNamespace = $this->groupByNamespace($failures);
        $recommendedAgents = min(count($groupedByNamespace), 4);

        echo "<agent_orchestration>\n";
        echo "  <summary>\n";
        echo sprintf("    <total_files_with_issues>%d</total_files_with_issues>\n", count($failures));
        echo sprintf("    <namespaces_affected>%d</namespaces_affected>\n", count($groupedByNamespace));
        echo sprintf("    <recommended_parallel_agents>%d</recommended_parallel_agents>\n", $recommendedAgents);
        echo "  </summary>\n\n";

        echo "  <parallel_strategy>\n";
        echo "    <instruction>Launch all agents simultaneously for maximum efficiency</instruction>\n\n";

        $agentNumber = 1;

        foreach ($groupedByNamespace as $namespace => $namespaceFailures) {
            $this->generateAgentTask($agentNumber++, $namespace, $namespaceFailures);
        }

        echo "  </parallel_strategy>\n\n";

        echo "  <sequential_alternative>\n";
        echo "    <instruction>If parallel execution unavailable, process in this order</instruction>\n";

        foreach ($failures as $index => $result) {
            echo sprintf(
                "    <file index=\"%d\" path=\"%s\">\n",
                $index + 1,
                $result->file->getPathname(),
            );

            foreach ($result->missing as $missing) {
                echo sprintf("      <missing_class>%s</missing_class>\n", $missing);
            }

            echo "    </file>\n";
        }

        echo "  </sequential_alternative>\n\n";

        echo "  <execution_instructions>\n";
        echo "    <step>Launch all agents simultaneously using your multi-agent orchestration tool</step>\n";
        echo "    <step>Each agent works independently on its assigned namespace</step>\n";
        echo "    <step>Monitor for completion and conflicts</step>\n";
        echo "    <step>Re-run analyzer to verify all issues resolved</step>\n";
        echo "  </execution_instructions>\n";
        echo "</agent_orchestration>\n";
    }

    /**
     * Group analysis failures by namespace for parallel processing.
     *
     * Organizes failures into namespace-based groups to enable parallel agent execution.
     * Each namespace represents an independent work unit that can be processed concurrently
     * without conflicts. Deduplicates results within each namespace group to prevent
     * redundant work assignments.
     *
     * @param  array<AnalysisResult>                $failures Analysis results containing missing class references
     * @return array<string, array<AnalysisResult>> Failures grouped by namespace, with namespace as key and unique results as values
     */
    private function groupByNamespace(array $failures): array
    {
        $grouped = [];

        foreach ($failures as $result) {
            foreach ($result->missing as $missing) {
                $namespace = $this->extractNamespace($missing);
                $grouped[$namespace] ??= [];
                $grouped[$namespace][] = $result;
            }
        }

        // Deduplicate results within each namespace group
        foreach ($grouped as $namespace => $results) {
            $grouped[$namespace] = array_values(array_unique($results, SORT_REGULAR));
        }

        return $grouped;
    }

    /**
     * Extract namespace from a fully qualified class name.
     *
     * Parses a fully qualified class name and returns its namespace portion by removing
     * the class name itself. Classes without namespaces (single-part names) are treated
     * as belonging to the global namespace for grouping purposes.
     *
     * @param  string $class Fully qualified class name (e.g., "App\Models\User")
     * @return string Namespace portion (e.g., "App\Models") or "(global)" for non-namespaced classes
     */
    private function extractNamespace(string $class): string
    {
        $parts = explode('\\', $class);

        if (count($parts) === 1) {
            return '(global)';
        }

        array_pop($parts);

        return implode('\\', $parts);
    }

    /**
     * Generate XML task definition for a single agent assignment.
     *
     * Creates a complete agent task specification including assigned namespace, affected
     * files, missing classes, and detailed remediation steps. Each agent receives a
     * namespace-scoped subset of the total failures, ensuring work can be performed
     * independently without coordination overhead or merge conflicts.
     *
     * @param int                   $agentNumber Sequential agent identifier for task organization
     * @param string                $namespace   Target namespace this agent is responsible for fixing
     * @param array<AnalysisResult> $failures    Analysis results containing failures within the assigned namespace
     */
    private function generateAgentTask(int $agentNumber, string $namespace, array $failures): void
    {
        $files = array_unique(array_map(
            fn (AnalysisResult $r): string => $r->file->getPathname(),
            $failures,
        ));

        $allMissing = array_unique(array_merge(...array_map(
            fn (AnalysisResult $r): array => array_filter(
                $r->missing,
                fn (string $class): bool => $this->extractNamespace($class) === $namespace,
            ),
            $failures,
        )));

        echo sprintf("    <agent id=\"%d\">\n", $agentNumber);
        echo sprintf("      <namespace>%s</namespace>\n", $namespace);
        echo sprintf("      <files_affected>%d</files_affected>\n", count($files));
        echo sprintf("      <missing_classes_count>%d</missing_classes_count>\n\n", count($allMissing));

        echo "      <task>\n";
        echo "        <objective>Fix missing class references in assigned files</objective>\n\n";
        echo "        <steps>\n";
        echo "          <step>Determine if each missing class is a typo, missing import, or missing dependency</step>\n";
        echo "          <step>Add proper use statements if the class exists elsewhere</step>\n";
        echo "          <step>Install missing packages via composer if needed</step>\n";
        echo "          <step>Fix typos in class names if applicable</step>\n";
        echo "          <step>Create stub classes if intentionally missing (mark with TODO)</step>\n";
        echo "        </steps>\n";
        echo "      </task>\n\n";

        echo "      <files>\n";

        foreach ($files as $file) {
            $result = array_values(array_filter(
                $failures,
                fn (AnalysisResult $r): bool => $r->file->getPathname() === $file,
            ))[0] ?? null;

            if ($result === null) {
                continue; // @codeCoverageIgnore
            }

            $relevantMissing = array_filter(
                $result->missing,
                fn (string $class): bool => $this->extractNamespace($class) === $namespace,
            );

            echo sprintf("        <file path=\"%s\">\n", $file);

            foreach ($relevantMissing as $missing) {
                echo sprintf("          <missing_class>%s</missing_class>\n", $missing);
            }

            echo "        </file>\n";
        }

        echo "      </files>\n\n";

        echo "      <expected_outcome>All files have valid class references with proper imports or dependencies installed</expected_outcome>\n";
        echo "    </agent>\n\n";
    }
}
