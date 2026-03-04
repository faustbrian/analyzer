<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;

test('it generates agent prompts for parallel fixing', function (): void {
    $config = AnalyzerConfig::make()
        ->paths([__DIR__.'/../Fixtures'])
        ->agentMode();

    $analyzer = new Analyzer($config);

    // Capture output
    ob_start();
    $results = $analyzer->analyze();
    $output = ob_get_clean();

    expect($results)->toBeArray()
        ->and($output)->toContain('<agent_orchestration>')
        ->and($output)->toContain('<summary>')
        ->and($output)->toContain('<parallel_strategy>')
        ->and($output)->toContain('<agent id="1">')
        ->and($output)->toContain('NonExistent')
        ->and($output)->toContain('Another')
        ->and($output)->toContain('<files_affected>')
        ->and($output)->toContain('<missing_class>');
});

test('agent mode shows success when no issues found', function (): void {
    $config = AnalyzerConfig::make()
        ->paths([__DIR__.'/../Fixtures/ValidClass.php'])
        ->agentMode();

    $analyzer = new Analyzer($config);

    // Capture output
    ob_start();
    $analyzer->analyze();
    $output = ob_get_clean();

    expect($output)->toContain('All class references exist - no fixes needed');
});
