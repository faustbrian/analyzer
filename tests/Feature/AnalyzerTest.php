<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analyzer;
use Cline\Analyzer\Config\AnalyzerConfig;
use Cline\Analyzer\Data\AnalysisResult;
use Cline\Analyzer\Processors\SerialProcessor;

test('it analyzes files and returns results', function (): void {
    $config = AnalyzerConfig::make()
        ->paths([__DIR__.'/../Fixtures'])
        ->processor(
            new SerialProcessor(),
        );

    $analyzer = new Analyzer($config);
    $results = $analyzer->analyze();

    expect($results)->toBeArray()
        ->and(count($results))->toBeGreaterThan(0);

    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(AnalysisResult::class);
    }
});

test('it detects failures', function (): void {
    $config = AnalyzerConfig::make()
        ->paths([__DIR__.'/../Fixtures'])
        ->processor(
            new SerialProcessor(),
        );

    $analyzer = new Analyzer($config);
    $results = $analyzer->analyze();

    expect($analyzer->hasFailures($results))->toBeTrue();
});

test('it works with parallel processing', function (): void {
    $config = AnalyzerConfig::make()
        ->paths([__DIR__.'/../Fixtures'])
        ->workers(2);

    $analyzer = new Analyzer($config);
    $results = $analyzer->analyze();

    expect($results)->toBeArray()
        ->and(count($results))->toBeGreaterThan(0);
});

test('it respects ignore patterns in analysis', function (): void {
    $config = AnalyzerConfig::make()
        ->paths([__DIR__.'/../Fixtures'])
        ->ignore(['NonExistent\\*', 'Another\\*', 'Tests\\Fixtures\\Routes\\*'])
        ->processor(
            new SerialProcessor(),
        );

    $analyzer = new Analyzer($config);
    $results = $analyzer->analyze();

    $hasOnlyIgnoredFailures = true;

    foreach ($results as $result) {
        if ($result->success) {
            continue;
        }

        if ($result->missing === []) {
            continue;
        }

        $hasOnlyIgnoredFailures = false;
    }

    expect($hasOnlyIgnoredFailures)->toBeTrue();
});
