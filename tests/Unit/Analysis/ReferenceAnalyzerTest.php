<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analysis\ReferenceAnalyzer;

test('it can analyze a valid file', function (): void {
    $analyzer = new ReferenceAnalyzer();
    $references = $analyzer->analyze(__DIR__.'/../../Fixtures/ValidClass.php');

    expect($references)->toBeArray()
        ->and($references)->toContain('InvalidArgumentException')
        ->and($references)->toContain('SplFileInfo');
});

test('it can analyze file with missing classes', function (): void {
    $analyzer = new ReferenceAnalyzer();
    $references = $analyzer->analyze(__DIR__.'/../../Fixtures/InvalidClass.php');

    expect($references)->toBeArray()
        ->and($references)->toContain('NonExistent\\FakeClass')
        ->and($references)->toContain('Another\\MissingClass');
});
