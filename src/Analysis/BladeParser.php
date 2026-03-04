<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Analysis;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Throwable;

use function file_get_contents;
use function function_exists;
use function resolve;
use function str_ends_with;
use function sys_get_temp_dir;

/**
 * Parses Blade templates into PHP code for AST analysis.
 *
 * Compiles Blade template syntax into standard PHP code using Laravel's BladeCompiler,
 * enabling PHP-Parser to analyze template files for class references, translation keys,
 * and route names. Handles all Blade directives including echo statements, control
 * structures, and component syntax.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class BladeParser
{
    /**
     * Blade compiler instance used for transforming Blade syntax to PHP.
     */
    private BladeCompiler $compiler;

    /**
     * Create a new Blade parser instance.
     *
     * Initializes the Blade compiler using dependency injection or by creating
     * a new instance with temporary cache directory. Supports Laravel service
     * container integration when available, with fallback to direct instantiation.
     *
     * @param null|BladeCompiler $compiler Optional compiler instance for dependency injection
     *                                     or testing purposes. When null, creates a new compiler
     *                                     instance using Laravel's Filesystem or standalone.
     */
    public function __construct(?BladeCompiler $compiler = null)
    {
        if ($compiler instanceof BladeCompiler) {
            $this->compiler = $compiler;
        } elseif (function_exists('app')) {
            try {
                $this->compiler = new BladeCompiler(
                    files: resolve(Filesystem::class),
                    cachePath: sys_get_temp_dir(),
                );
            } catch (Throwable) {
                // Fall back to creating Filesystem directly
                $this->compiler = new BladeCompiler(
                    files: new Filesystem(),
                    cachePath: sys_get_temp_dir(),
                );
            }
        } else {
            // @codeCoverageIgnoreStart
            // This fallback path cannot be tested in Laravel test environment because
            // app() function is always available when running tests through Orchestra/Testbench.
            // This defensive code handles standalone PHP environments where Laravel helpers
            // are not loaded.
            $this->compiler = new BladeCompiler(
                files: new Filesystem(),
                cachePath: sys_get_temp_dir(),
            );
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Check if a file is a Blade template.
     *
     * @param  string $path File path to check
     * @return bool   True if file has .blade.php extension
     */
    public static function isBladeFile(string $path): bool
    {
        return str_ends_with($path, '.blade.php');
    }

    /**
     * Parse Blade template content into PHP code.
     *
     * Compiles Blade syntax into standard PHP code that can be parsed by PHP-Parser.
     * Handles all Blade features including echo statements, directives, components,
     * and raw PHP blocks.
     *
     * @param  string $content Blade template content to parse
     * @return string Compiled PHP code
     */
    public function parse(string $content): string
    {
        return $this->compiler->compileString($content);
    }

    /**
     * Compile Blade template content into PHP code.
     *
     * Alias for parse() method for backwards compatibility.
     *
     * @param  string $content Blade template content to compile
     * @return string Compiled PHP code
     */
    public function compile(string $content): string
    {
        return $this->parse($content);
    }

    /**
     * Parse a Blade file into PHP code.
     *
     * Reads the file content from disk and compiles it using the Blade compiler.
     * Supports both regular PHP files and Blade template files with .blade.php extension.
     *
     * @param  string $path Absolute path to Blade template file to compile
     * @return string Compiled PHP code ready for AST parsing and analysis
     */
    public function parseFile(string $path): string
    {
        $content = (string) file_get_contents($path);

        return $this->parse($content);
    }
}
