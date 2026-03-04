<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Data\AnalysisResult;
use Cline\Analyzer\Reporters\AgentReporter;
use Illuminate\Support\Str;

describe('AgentReporter', function (): void {
    beforeEach(function (): void {
        $this->reporter = new AgentReporter();
    });

    describe('start', function (): void {
        it('produces no output during start phase', function (): void {
            // Arrange
            $files = [
                new SplFileInfo(__DIR__.'/../../Fixtures/ValidClass.php'),
                new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php'),
            ];

            // Act
            ob_start();
            $this->reporter->start($files);
            $output = ob_get_clean();

            // Assert
            expect($output)->toBe('');
        });
    });

    describe('progress', function (): void {
        it('produces no output during progress phase', function (): void {
            // Arrange
            $result = new AnalysisResult(
                file: new SplFileInfo(__DIR__.'/../../Fixtures/ValidClass.php'),
                references: [],
                missing: [],
                success: true,
            );

            // Act
            ob_start();
            $this->reporter->progress($result);
            $output = ob_get_clean();

            // Assert
            expect($output)->toBe('');
        });
    });

    describe('finish', function (): void {
        it('displays success message when no failures exist', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo(__DIR__.'/../../Fixtures/ValidClass.php'),
                    references: [],
                    missing: [],
                    success: true,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)->toBe("✓ All class references exist - no fixes needed.\n");
        });

        it('generates agent orchestration prompt when failures exist', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php'),
                    references: [],
                    missing: ['App\Models\User', 'App\Services\PaymentService'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<agent_orchestration>')
                ->toContain('<total_files_with_issues>1</total_files_with_issues>')
                ->toContain('<namespaces_affected>2</namespaces_affected>')
                ->toContain('<recommended_parallel_agents>2</recommended_parallel_agents>')
                ->toContain('</agent_orchestration>');
        });

        it('groups failures by namespace correctly', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file1.php'),
                    references: [],
                    missing: ['App\Models\User', 'App\Models\Post'],
                    success: false,
                ),
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file2.php'),
                    references: [],
                    missing: ['App\Services\PaymentService'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<namespace>App\Models</namespace>')
                ->toContain('<namespace>App\Services</namespace>')
                ->toContain('<namespaces_affected>2</namespaces_affected>');
        });

        it('caps recommended agents at 4 even with more namespaces', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file1.php'),
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file2.php'),
                    references: [],
                    missing: ['App\Services\PaymentService'],
                    success: false,
                ),
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file3.php'),
                    references: [],
                    missing: ['App\Controllers\HomeController'],
                    success: false,
                ),
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file4.php'),
                    references: [],
                    missing: ['App\Repositories\UserRepository'],
                    success: false,
                ),
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file5.php'),
                    references: [],
                    missing: ['App\Events\UserCreated'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<recommended_parallel_agents>4</recommended_parallel_agents>')
                ->toContain('<namespaces_affected>5</namespaces_affected>');
        });

        it('handles classes without namespace using global namespace', function (): void {
            // Arrange - This tests line 197
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['GlobalClass'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<namespace>(global)</namespace>')
                ->toContain('<missing_class>GlobalClass</missing_class>');
        });

        it('generates parallel strategy with agent tasks', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<parallel_strategy>')
                ->toContain('<instruction>Launch all agents simultaneously for maximum efficiency</instruction>')
                ->toContain('<agent id="1">')
                ->toContain('</parallel_strategy>');
        });

        it('generates sequential alternative section', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<sequential_alternative>')
                ->toContain('<instruction>If parallel execution unavailable, process in this order</instruction>')
                ->toContain('<file index="1" path="/path/to/file.php">')
                ->toContain('</sequential_alternative>');
        });

        it('generates execution instructions', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<execution_instructions>')
                ->toContain('<step>Launch all agents simultaneously using your multi-agent orchestration tool</step>')
                ->toContain('<step>Each agent works independently on its assigned namespace</step>')
                ->toContain('<step>Monitor for completion and conflicts</step>')
                ->toContain('<step>Re-run analyzer to verify all issues resolved</step>')
                ->toContain('</execution_instructions>');
        });

        it('includes task objectives and steps in agent tasks', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<task>')
                ->toContain('<objective>Fix missing class references in assigned files</objective>')
                ->toContain('<steps>')
                ->toContain('<step>Determine if each missing class is a typo, missing import, or missing dependency</step>')
                ->toContain('<step>Add proper use statements if the class exists elsewhere</step>')
                ->toContain('<step>Install missing packages via composer if needed</step>')
                ->toContain('<step>Fix typos in class names if applicable</step>')
                ->toContain('<step>Create stub classes if intentionally missing (mark with TODO)</step>')
                ->toContain('</steps>')
                ->toContain('</task>');
        });

        it('includes expected outcome in agent tasks', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<expected_outcome>All files have valid class references with proper imports or dependencies installed</expected_outcome>');
        });

        it('deduplicates results within namespace groups', function (): void {
            // Arrange
            $file = new SplFileInfo('/path/to/file.php');
            $results = [
                new AnalysisResult(
                    file: $file,
                    references: [],
                    missing: ['App\Models\User', 'App\Models\Post'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert - File should only appear once per namespace
            expect($output)
                ->toContain('<files_affected>1</files_affected>');
        });

        it('filters missing classes by namespace in agent tasks', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['App\Models\User', 'App\Services\PaymentService'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert - Each agent should only see classes in its namespace
            expect($output)
                ->toContain('<agent id="1">')
                ->toContain('<namespace>App\Models</namespace>')
                ->toContain('<missing_classes_count>1</missing_classes_count>')
                ->and($output)
                ->toContain('<agent id="2">')
                ->toContain('<namespace>App\Services</namespace>')
                ->toContain('<missing_classes_count>1</missing_classes_count>');
        });

        it('handles multiple files in same namespace', function (): void {
            // Arrange - Multiple files with missing classes in the same namespace
            $file1 = new SplFileInfo('/path/to/file1.php');
            $file2 = new SplFileInfo('/path/to/file2.php');

            $results = [
                new AnalysisResult(
                    file: $file1,
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
                new AnalysisResult(
                    file: $file2,
                    references: [],
                    missing: ['App\Models\Post'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert - Should successfully process both files
            expect($output)
                ->toContain('<agent id="1">')
                ->toContain('<namespace>App\Models</namespace>')
                ->toContain('<file path="/path/to/file1.php">')
                ->toContain('<file path="/path/to/file2.php">')
                ->toContain('<missing_class>App\Models\User</missing_class>')
                ->toContain('<missing_class>App\Models\Post</missing_class>');
        });

        it('generates XML output with proper structure and formatting', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert - Verify XML structure
            expect($output)
                ->toContain('<agent_orchestration>')
                ->toContain('  <summary>')
                ->toContain('  </summary>')
                ->toContain('  <parallel_strategy>')
                ->toContain('  </parallel_strategy>')
                ->toContain('  <sequential_alternative>')
                ->toContain('  </sequential_alternative>')
                ->toContain('  <execution_instructions>')
                ->toContain('  </execution_instructions>')
                ->toContain('</agent_orchestration>');
        });

        it('handles multiple files with mixed namespaces', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file1.php'),
                    references: [],
                    missing: ['App\Models\User', 'App\Services\UserService'],
                    success: false,
                ),
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file2.php'),
                    references: [],
                    missing: ['App\Models\Post', 'App\Controllers\PostController'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<total_files_with_issues>2</total_files_with_issues>')
                ->toContain('<namespaces_affected>3</namespaces_affected>')
                ->toContain('<namespace>App\Models</namespace>')
                ->toContain('<namespace>App\Services</namespace>')
                ->toContain('<namespace>App\Controllers</namespace>');
        });

        it('correctly counts missing classes per namespace', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file1.php'),
                    references: [],
                    missing: ['App\Models\User', 'App\Models\Post', 'App\Models\Comment'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<namespace>App\Models</namespace>')
                ->toContain('<missing_classes_count>3</missing_classes_count>');
        });

        it('handles empty results array', function (): void {
            // Arrange
            $results = [];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)->toBe("✓ All class references exist - no fixes needed.\n");
        });

        it('handles mixed success and failure results', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/valid.php'),
                    references: [],
                    missing: [],
                    success: true,
                ),
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/invalid.php'),
                    references: [],
                    missing: ['App\Models\User'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert - Should only show the failure
            expect($output)
                ->toContain('<total_files_with_issues>1</total_files_with_issues>')
                ->toContain('<agent_orchestration>');
        });

        it('handles complex namespace grouping with single-part class names', function (): void {
            // Arrange - Mix of namespaced and non-namespaced classes
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/legacy.php'),
                    references: [],
                    missing: ['stdClass', 'Exception'],
                    success: false,
                ),
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/modern.php'),
                    references: [],
                    missing: ['App\Models\User', 'App\Models\Post'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert - Should handle both global and namespaced classes
            expect($output)
                ->toContain('<namespace>(global)</namespace>')
                ->toContain('<namespace>App\Models</namespace>')
                ->toContain('<missing_class>stdClass</missing_class>')
                ->toContain('<missing_class>Exception</missing_class>')
                ->toContain('<missing_class>App\Models\User</missing_class>')
                ->toContain('<missing_class>App\Models\Post</missing_class>');
        });

        it('handles file with classes from multiple namespaces', function (): void {
            // Arrange - Single file with missing classes from different namespaces
            $file = new SplFileInfo('/path/to/complex.php');
            $results = [
                new AnalysisResult(
                    file: $file,
                    references: [],
                    missing: [
                        'App\Models\User',
                        'App\Services\UserService',
                        'App\Controllers\UserController',
                        Str::class,
                    ],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert - File should appear in each namespace's agent task
            expect($output)
                ->toContain('<agent id="1">')
                ->toContain('<agent id="2">')
                ->toContain('<agent id="3">')
                ->toContain('<agent id="4">')
                ->toContain('<namespace>App\Models</namespace>')
                ->toContain('<namespace>App\Services</namespace>')
                ->toContain('<namespace>App\Controllers</namespace>')
                ->toContain('<namespace>Illuminate\Support</namespace>');
        });

        it('generates correct XML for deeply nested namespaces', function (): void {
            // Arrange
            $results = [
                new AnalysisResult(
                    file: new SplFileInfo('/path/to/file.php'),
                    references: [],
                    missing: ['Very\Deep\Nested\Namespace\Structure\SomeClass'],
                    success: false,
                ),
            ];

            // Act
            ob_start();
            $this->reporter->finish($results);
            $output = ob_get_clean();

            // Assert
            expect($output)
                ->toContain('<namespace>Very\Deep\Nested\Namespace\Structure</namespace>')
                ->toContain('<missing_class>Very\Deep\Nested\Namespace\Structure\SomeClass</missing_class>');
        });
    });
});
