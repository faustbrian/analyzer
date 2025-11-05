<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Analysis;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

use function array_merge;
use function array_unique;
use function array_values;
use function file_get_contents;

/**
 * Analyzes PHP files to extract all class references.
 *
 * Orchestrates multiple AST visitors to comprehensively identify class dependencies
 * by combining use statement imports, fully qualified name references, and PHPDoc
 * type annotations. Parses PHP source code into an AST, traverses it with specialized
 * visitors, and merges results into a deduplicated list of referenced classes.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ReferenceAnalyzer
{
    /**
     * PHP parser instance for converting source code to AST.
     */
    private Parser $parser;

    /**
     * Create a new reference analyzer instance.
     *
     * @param null|Parser $parser Optional PHP parser instance. If not provided, creates a parser
     *                            configured for the newest supported PHP version using ParserFactory.
     *                            Allows injection of custom parsers for testing or specialized parsing.
     */
    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? new ParserFactory()->createForNewestSupportedVersion();
    }

    /**
     * Analyze a PHP file to extract all referenced class names.
     *
     * Parses the file into an AST and traverses it with multiple visitors to collect
     * class references from three sources: use statements (ImportVisitor), fully qualified
     * names in code (NameVisitor), and type annotations in PHPDoc blocks (DocVisitor +
     * DocProcessor). Combines and deduplicates all references into a single list.
     *
     * @param  string        $path Absolute path to the PHP file to analyze
     * @return array<string> Deduplicated list of fully qualified class names referenced in the file
     */
    public function analyze(string $path): array
    {
        $contents = (string) file_get_contents($path);

        // Configure AST traverser with name resolution and collection visitors
        $traverser = new NodeTraverser();
        $traverser->addVisitor(
            new NameResolver(), // Resolves relative names to fully qualified names
        );
        $traverser->addVisitor($imports = new ImportVisitor());
        $traverser->addVisitor($names = new NameVisitor());
        $traverser->addVisitor($docs = DocVisitor::create($contents));

        $traverser->traverse($this->parser->parse($contents) ?? []);

        // Merge and deduplicate references from all sources
        return array_values(array_unique(array_merge(
            $imports->getImports() ?? [],
            $names->getNames() ?? [],
            DocProcessor::process($docs->getDoc() ?? []),
        )));
    }
}
