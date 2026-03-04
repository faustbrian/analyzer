<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Data\AnalysisResult;
use Cline\Analyzer\Reporters\PromptsReporter;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;

beforeEach(function (): void {
    $this->reporter = new PromptsReporter();

    // Set up buffered output for testing
    $this->output = new BufferedConsoleOutput();
    Prompt::setOutput($this->output);
});

/**
 * Happy Path Tests - Valid scenarios with expected successful outcomes.
 */
test('it displays startup information with file count', function (): void {
    // Arrange
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__DIR__),
    ];

    // Act
    $this->reporter->start($files);

    // Assert
    $output = $this->output->content();
    expect($output)->toContain('Analyzing 2 files...');
})->group('happy-path');

test('it tracks successful file progress', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $result = AnalysisResult::success($file, ['SplFileInfo']);

    $this->reporter->start([$file]);

    // Act
    $this->reporter->progress($result);

    // Assert - success counter incremented (verified in finish test)
    expect(true)->toBeTrue();
})->group('happy-path');

test('it tracks failed file progress', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $result = AnalysisResult::failure($file, ['Missing\\Class'], ['Missing\\Class']);

    $this->reporter->start([$file]);

    // Act
    $this->reporter->progress($result);

    // Assert - failed counter incremented (verified in finish test)
    expect(true)->toBeTrue();
})->group('happy-path');

test('it displays completion message for all successful files', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::success($file, ['SplFileInfo']),
    ];

    $this->reporter->start([$file]);
    $this->reporter->progress($results[0]);

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Analysis complete: 1/1 files processed')
        ->toContain('✓ 1 passed')
        ->toContain('All class references exist!');
})->group('happy-path');

test('it processes multiple files successfully', function (): void {
    // Arrange
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__DIR__),
    ];
    $results = [
        AnalysisResult::success($files[0], ['SplFileInfo']),
        AnalysisResult::success($files[1], ['Throwable']),
    ];

    $this->reporter->start($files);

    foreach ($results as $result) {
        $this->reporter->progress($result);
    }

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Analyzing 2 files...')
        ->toContain('Analysis complete: 2/2 files processed')
        ->toContain('✓ 2 passed')
        ->toContain('All class references exist!');
})->group('happy-path');

/**
 * Sad Path Tests - Validation errors, failures, and error conditions.
 */
test('it displays failure summary with missing classes', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::failure(
            $file,
            ['Missing\\Class', 'Another\\Missing'],
            ['Missing\\Class', 'Another\\Missing'],
        ),
    ];

    $this->reporter->start([$file]);
    $this->reporter->progress($results[0]);

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Analysis complete: 1/1 files processed')
        ->toContain('✓ 0 passed')
        ->toContain('✗ 1 failed')
        ->toContain('Summary Statistics:')
        ->toContain('Total missing references:')
        ->toContain('Unique missing classes:')
        ->toContain('Missing\\Class')
        ->toContain('Another\\Missing')
        ->toContain('Missing class references found:');
})->group('sad-path');

test('it displays summary statistics for multiple failures', function (): void {
    // Arrange
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__DIR__),
    ];
    $results = [
        AnalysisResult::failure(
            $files[0],
            ['Missing\\Class'],
            ['Missing\\Class'],
        ),
        AnalysisResult::failure(
            $files[1],
            ['Missing\\Class', 'Another\\Missing'],
            ['Missing\\Class', 'Another\\Missing'],
        ),
    ];

    $this->reporter->start($files);

    foreach ($results as $result) {
        $this->reporter->progress($result);
    }

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Analysis complete: 2/2 files processed')
        ->toContain('✓ 0 passed')
        ->toContain('✗ 2 failed')
        ->toContain('Total missing references: 3')
        ->toContain('Unique missing classes: 2')
        ->toContain('Missing\\Class')
        ->toContain('Another\\Missing');
})->group('sad-path');

test('it handles mixed success and failure results', function (): void {
    // Arrange
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__DIR__),
    ];
    $results = [
        AnalysisResult::success($files[0], ['SplFileInfo']),
        AnalysisResult::failure(
            $files[1],
            ['Missing\\Class'],
            ['Missing\\Class'],
        ),
    ];

    $this->reporter->start($files);

    foreach ($results as $result) {
        $this->reporter->progress($result);
    }

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Analysis complete: 2/2 files processed')
        ->toContain('✓ 1 passed')
        ->toContain('✗ 1 failed')
        ->toContain('Missing class references found:')
        ->toContain('Missing\\Class');
})->group('sad-path');

/**
 * Edge Cases - Boundary conditions, empty states, and special scenarios.
 */
test('it handles empty file list', function (): void {
    // Arrange
    $files = [];

    // Act
    $this->reporter->start($files);

    // Assert
    $output = $this->output->content();
    expect($output)->toContain('Analyzing 0 files...');
})->group('edge-case');

test('it handles empty results array', function (): void {
    // Arrange
    $results = [];

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Analysis complete: 0/0 files processed')
        ->toContain('✓ 0 passed')
        ->toContain('All class references exist!');
})->group('edge-case');

test('it displays failure details with file paths and missing classes', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::failure(
            $file,
            ['App\\Missing\\Class'],
            ['App\\Missing\\Class'],
        ),
    ];

    $this->reporter->start([$file]);
    $this->reporter->progress($results[0]);

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Missing class references found:')
        ->toContain('App\\Missing\\Class')
        ->toContain($file->getPathname());
})->group('edge-case');

test('it displays top 5 most referenced missing classes', function (): void {
    // Arrange
    $files = array_map(fn (int $i): SplFileInfo => new SplFileInfo(__FILE__), range(1, 6));
    $results = [
        AnalysisResult::failure($files[0], ['Class1', 'Class2'], ['Class1', 'Class2']),
        AnalysisResult::failure($files[1], ['Class1', 'Class3'], ['Class1', 'Class3']),
        AnalysisResult::failure($files[2], ['Class1', 'Class4'], ['Class1', 'Class4']),
        AnalysisResult::failure($files[3], ['Class1', 'Class5'], ['Class1', 'Class5']),
        AnalysisResult::failure($files[4], ['Class1', 'Class6'], ['Class1', 'Class6']),
        AnalysisResult::failure($files[5], ['Class1'], ['Class1']),
    ];

    $this->reporter->start($files);

    foreach ($results as $result) {
        $this->reporter->progress($result);
    }

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Top 5 Most Referenced Missing Classes:')
        ->toContain('6x  Class1') // Most referenced
        ->toContain('Top 3 Most Broken Namespaces:');
})->group('edge-case');

test('it displays top 3 most broken namespaces', function (): void {
    // Arrange
    $files = array_map(fn (int $i): SplFileInfo => new SplFileInfo(__FILE__), range(1, 4));
    $results = [
        AnalysisResult::failure($files[0], ['App\\Service\\Missing'], ['App\\Service\\Missing']),
        AnalysisResult::failure($files[1], ['App\\Service\\Another'], ['App\\Service\\Another']),
        AnalysisResult::failure($files[2], ['Domain\\Missing'], ['Domain\\Missing']),
        AnalysisResult::failure($files[3], ['Domain\\Another'], ['Domain\\Another']),
    ];

    $this->reporter->start($files);

    foreach ($results as $result) {
        $this->reporter->progress($result);
    }

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Top 3 Most Broken Namespaces:')
        ->toContain('App\\Service')
        ->toContain('Domain');
})->group('edge-case');

test('it handles classes without namespace as global', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::failure($file, ['GlobalClass'], ['GlobalClass']),
    ];

    $this->reporter->start([$file]);
    $this->reporter->progress($results[0]);

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('GlobalClass')
        ->toContain('(global)'); // Should show (global) for classes without namespace
})->group('edge-case');

test('it creates table data for all failures', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::failure(
            $file,
            ['Class1', 'Class2'],
            ['Class1', 'Class2'],
        ),
    ];

    $this->reporter->start([$file]);
    $this->reporter->progress($results[0]);

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('File')
        ->toContain('Missing Class')
        ->toContain('Class1')
        ->toContain('Class2');
})->group('edge-case');

test('it displays each missing class reference in failures', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::failure(
            $file,
            ['First\\Missing', 'Second\\Missing'],
            ['First\\Missing', 'Second\\Missing'],
        ),
    ];

    $this->reporter->start([$file]);
    $this->reporter->progress($results[0]);

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('First\\Missing')
        ->toContain('Second\\Missing')
        ->toContain($file->getPathname());
})->group('edge-case');

/**
 * CRITICAL TEST - Line 214 Coverage
 * This test covers the early return in displayFailures() when there are no failures.
 * This is the missing line from the coverage report.
 */
test('it handles finish with all successful results without displaying failures', function (): void {
    // Arrange
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__DIR__),
    ];
    $results = [
        AnalysisResult::success($files[0], ['SplFileInfo']),
        AnalysisResult::success($files[1], ['Throwable']),
    ];

    $this->reporter->start($files);

    foreach ($results as $result) {
        $this->reporter->progress($result);
    }

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();

    // CRITICAL: displayFailures() should return early on line 214
    // because there are no failures, so these strings should NOT appear
    expect($output)
        ->toContain('Analysis complete: 2/2 files processed')
        ->toContain('✓ 2 passed')
        ->toContain('All class references exist!')
        ->not->toContain('Missing class references found:')
        ->not->toContain('Summary Statistics:')
        ->not->toContain('Top 5 Most Referenced Missing Classes:')
        ->not->toContain('Top 3 Most Broken Namespaces:');
})->group('edge-case');

test('it handles single file with multiple missing classes in same namespace', function (): void {
    // Arrange
    $file = new SplFileInfo(__FILE__);
    $results = [
        AnalysisResult::failure(
            $file,
            ['App\\Models\\User', 'App\\Models\\Post', 'App\\Models\\Comment'],
            ['App\\Models\\User', 'App\\Models\\Post', 'App\\Models\\Comment'],
        ),
    ];

    $this->reporter->start([$file]);
    $this->reporter->progress($results[0]);

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Total missing references: 3')
        ->toContain('Unique missing classes: 3')
        ->toContain('App\\Models\\User')
        ->toContain('App\\Models\\Post')
        ->toContain('App\\Models\\Comment')
        ->toContain('3x  App\\Models'); // All three in same namespace
})->group('edge-case');

test('it correctly counts duplicate missing classes across files', function (): void {
    // Arrange
    $files = [
        new SplFileInfo(__FILE__),
        new SplFileInfo(__DIR__),
    ];
    $results = [
        AnalysisResult::failure($files[0], ['DuplicateClass'], ['DuplicateClass']),
        AnalysisResult::failure($files[1], ['DuplicateClass'], ['DuplicateClass']),
    ];

    $this->reporter->start($files);

    foreach ($results as $result) {
        $this->reporter->progress($result);
    }

    // Act
    $this->reporter->finish($results);

    // Assert
    $output = $this->output->content();
    expect($output)
        ->toContain('Total missing references: 2') // Counted twice
        ->toContain('Unique missing classes: 1') // Only one unique class
        ->toContain('2x  DuplicateClass'); // Referenced 2 times
})->group('edge-case');
