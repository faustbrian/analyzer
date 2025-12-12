<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Analyzer\AnalyzerServiceProvider;
use Cline\Analyzer\Processors\SerialProcessor;
use Cline\Analyzer\Reporters\PromptsReporter;
use Cline\Analyzer\Resolvers\AnalysisResolver;
use Cline\Analyzer\Resolvers\FileResolver;
use Cline\Analyzer\Resolvers\PathResolver;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

use function config;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends Orchestra
{
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AnalyzerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set up test config
        config()->set('analyzer.paths', ['app', 'tests']);
        config()->set('analyzer.workers', 4); // Use 4 instead of 'auto' to avoid division by zero
        config()->set('analyzer.ignore', ['Illuminate\\*', 'Laravel\\*', 'Symfony\\*']);
        config()->set('analyzer.path_resolver', PathResolver::class);
        config()->set('analyzer.file_resolver', FileResolver::class);
        config()->set('analyzer.analysis_resolver', AnalysisResolver::class);
        config()->set('analyzer.reporter', PromptsReporter::class);
        config()->set('analyzer.processor', SerialProcessor::class); // Use serial by default for tests
    }
}
