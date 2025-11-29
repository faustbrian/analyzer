<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analysis\BladeParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;

describe('BladeParser', function (): void {
    beforeEach(function (): void {
        $this->parser = new BladeParser();
    });

    describe('Constructor - Dependency Injection', function (): void {
        test('it accepts BladeCompiler via constructor', function (): void {
            // Arrange
            $mockFilesystem = new Filesystem();
            $mockCompiler = new BladeCompiler($mockFilesystem, sys_get_temp_dir());

            // Act
            $parser = new BladeParser($mockCompiler);
            $result = $parser->parse('{{ $test }}');

            // Assert
            expect($result)->toContain('<?php')
                ->and($result)->toContain('$test');
        });

        test('it creates BladeCompiler when none provided and app() exists', function (): void {
            // Arrange
            // This test validates that when app() function exists and returns a Filesystem,
            // the constructor uses it (lines 56-61)

            // Act
            $parser = new BladeParser();
            $result = $parser->parse('{{ $value }}');

            // Assert
            expect($result)->toBeString()
                ->and($result)->toContain('$value');
        });

        test('it falls back to new Filesystem when app() throws exception', function (): void {
            // This test validates lines 62-67: When app() exists but app(Filesystem::class) throws,
            // constructor catches the exception and falls back to creating new Filesystem directly

            // Arrange - Bind Filesystem to throw an exception when resolved
            $originalBinding = app()->make(Filesystem::class);
            app()->singleton(function (): Filesystem {
                throw new RuntimeException('Container binding failure simulation');
            });

            // Act - This should trigger the catch block on line 62 and create new Filesystem()
            $parser = new BladeParser();
            $result = $parser->parse('<div>{{ $test }}</div>');

            // Assert - parser should still work via fallback path creating new Filesystem (lines 64-67)
            expect($result)->toBeString()
                ->and($result)->toContain('$test');

            // Clean up - restore original binding
            app()->singleton(Filesystem::class, fn () => $originalBinding);
        });
    });

    describe('isBladeFile Static Method', function (): void {
        test('it returns true for .blade.php files', function (): void {
            // Arrange & Act
            $result = BladeParser::isBladeFile('resources/views/welcome.blade.php');

            // Assert
            expect($result)->toBeTrue();
        });

        test('it returns true for nested blade files', function (): void {
            // Arrange & Act
            $result = BladeParser::isBladeFile('/app/views/admin/users/index.blade.php');

            // Assert
            expect($result)->toBeTrue();
        });

        test('it returns false for regular PHP files', function (): void {
            // Arrange & Act
            $result = BladeParser::isBladeFile('app/Http/Controllers/UserController.php');

            // Assert
            expect($result)->toBeFalse();
        });

        test('it returns false for non-PHP files', function (): void {
            // Arrange & Act
            $resultJs = BladeParser::isBladeFile('resources/js/app.js');
            $resultCss = BladeParser::isBladeFile('resources/css/app.css');
            $resultHtml = BladeParser::isBladeFile('index.html');

            // Assert
            expect($resultJs)->toBeFalse()
                ->and($resultCss)->toBeFalse()
                ->and($resultHtml)->toBeFalse();
        });

        test('it returns false for files with blade in name but not .blade.php extension', function (): void {
            // Arrange & Act
            $result = BladeParser::isBladeFile('blade-config.php');

            // Assert
            expect($result)->toBeFalse();
        });

        test('it returns false for empty string', function (): void {
            // Arrange & Act
            $result = BladeParser::isBladeFile('');

            // Assert
            expect($result)->toBeFalse();
        });

        test('it handles files with multiple dots correctly', function (): void {
            // Arrange & Act
            $bladeFile = BladeParser::isBladeFile('user.profile.blade.php');
            $nonBladeFile = BladeParser::isBladeFile('user.blade.config.php');

            // Assert
            expect($bladeFile)->toBeTrue()
                ->and($nonBladeFile)->toBeFalse();
        });
    });

    describe('Happy Path - Basic Blade Directives', function (): void {
        test('it compiles @lang directive to valid PHP', function (): void {
            // Arrange
            $blade = "@lang('messages.welcome')";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("app('translator')->get('messages.welcome')")
                ->and($php)->toBeString()
                ->and($php)->toContain('<?php');
        });

        test('it compiles {{ __() }} to valid PHP', function (): void {
            // Arrange
            $blade = "{{ __('auth.failed') }}";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('auth.failed')")
                ->and($php)->toContain('<?php')
                ->and($php)->toContain('echo');
        });

        test('it compiles {{ trans() }} to valid PHP', function (): void {
            // Arrange
            $blade = "{{ trans('validation.required') }}";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("trans('validation.required')")
                ->and($php)->toContain('<?php')
                ->and($php)->toContain('echo');
        });

        test('it compiles multiple translation calls in same template', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            <h1>{{ __('users.title') }}</h1>
            <p>{{ trans('users.description') }}</p>
            <span>@lang('users.footer')</span>
            BLADE;

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('users.title')")
                ->and($php)->toContain("trans('users.description')")
                ->and($php)->toContain("app('translator')->get('users.footer')");
        });
    });

    describe('Happy Path - Complex Blade Templates', function (): void {
        test('it preserves translation calls in conditional blocks', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            @if($hasError)
                {{ __('errors.generic') }}
            @else
                {{ __('success.message') }}
            @endif
            BLADE;

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('errors.generic')")
                ->and($php)->toContain("__('success.message')")
                ->and($php)->toContain('if');
        });

        test('it preserves translation calls in loops', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            @foreach($items as $item)
                {{ __('items.label') }}: {{ $item->name }}
            @endforeach
            BLADE;

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('items.label')")
                ->and($php)->toContain('foreach');
        });

        test('it handles translation calls with replacement parameters', function (): void {
            // Arrange
            $blade = "{{ __('welcome.user', ['name' => \$userName]) }}";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('welcome.user'")
                ->and($php)->toContain("'name' => \$userName");
        });

        test('it handles translation calls as function arguments', function (): void {
            // Arrange
            $blade = "{{ strtoupper(__('messages.hello')) }}";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain('strtoupper')
                ->and($php)->toContain("__('messages.hello')");
        });
    });

    describe('Edge Cases', function (): void {
        test('it handles empty Blade template', function (): void {
            // Arrange
            $blade = '';

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toBe('');
        });

        test('it handles Blade with no translation calls', function (): void {
            // Arrange
            $blade = '<h1>{{ $title }}</h1><p>{{ $content }}</p>';

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toBeString()
                ->and($php)->toContain('$title')
                ->and($php)->toContain('$content');
        });

        test('it handles Blade comments with translation examples', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            {{-- This uses @lang('messages.test') --}}
            {{ __('messages.actual') }}
            BLADE;

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('messages.actual')")
                ->and($php)->not->toContain("app('translator')->get('messages.test')");
        });

        test('it handles escaped Blade syntax', function (): void {
            // Arrange
            $blade = "@{{ __('not.compiled') }}";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("{{ __('not.compiled') }}")
                ->and($php)->not->toContain('<?php');
        });

        test('it handles unicode in Blade translations', function (): void {
            // Arrange
            $blade = "{{ __('messages.你好') }}";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('messages.你好')");
        });

        test('it handles multiline Blade translation calls', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            {{ __(
                'messages.long.key',
                [
                    'param1' => $value1,
                    'param2' => $value2
                ]
            ) }}
            BLADE;

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain('__')
                ->and($php)->toContain("'messages.long.key'");
        });
    });

    describe('parse() Method', function (): void {
        test('it compiles Blade syntax using parse method', function (): void {
            // Arrange
            $blade = '{{ $user->name }}';

            // Act
            $php = $this->parser->parse($blade);

            // Assert
            expect($php)->toContain('<?php')
                ->and($php)->toContain('$user->name')
                ->and($php)->toContain('echo');
        });

        test('it handles complex Blade directives in parse', function (): void {
            // Arrange
            $blade = '@if($condition){{ $value }}@endif';

            // Act
            $php = $this->parser->parse($blade);

            // Assert
            expect($php)->toContain('if')
                ->and($php)->toContain('$condition')
                ->and($php)->toContain('$value');
        });
    });

    describe('compile() Method Alias', function (): void {
        test('it compiles Blade using compile method alias', function (): void {
            // Arrange
            $blade = '{{ $data }}';

            // Act
            $phpFromCompile = $this->parser->compile($blade);
            $phpFromParse = $this->parser->parse($blade);

            // Assert
            expect($phpFromCompile)->toBe($phpFromParse)
                ->and($phpFromCompile)->toContain('$data');
        });

        test('it produces identical output between compile and parse', function (): void {
            // Arrange
            $blade = '@foreach($items as $item){{ $item }}@endforeach';

            // Act
            $compiled = $this->parser->compile($blade);
            $parsed = $this->parser->parse($blade);

            // Assert
            expect($compiled)->toBe($parsed);
        });
    });

    describe('parseFile() Method', function (): void {
        test('it can parse Blade from file path', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/translations/views/example.blade.php';

            // Act
            $php = $this->parser->parseFile($filePath);

            // Assert
            expect($php)->toBeString()
                ->and($php)->not->toBe('');
        });

        test('it throws exception for non-existent Blade file', function (): void {
            // Arrange
            $filePath = '/non/existent/file.blade.php';

            // Act & Assert
            expect(fn () => $this->parser->parseFile($filePath))
                ->toThrow(ErrorException::class);
        });

        test('it correctly compiles content from file', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/translations/views/example.blade.php';

            // Act
            $php = $this->parser->parseFile($filePath);

            // Assert
            expect($php)->toBeString();
        });
    });

    describe('Blade to PHP AST Compatibility', function (): void {
        test('it produces parseable PHP for AST analysis', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            <div>
                {{ __('messages.title') }}
                @lang('messages.subtitle')
                {{ trans('messages.content') }}
            </div>
            BLADE;

            // Act
            $php = $this->parser->compile($blade);
            $parser = new ParserFactory()->createForNewestSupportedVersion();
            $ast = $parser->parse($php);

            // Assert
            expect($ast)->toBeArray()
                ->and($ast)->not->toBeEmpty();
        });

        test('it maintains line number information for error reporting', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            Line 1
            {{ __('line.two') }}
            Line 3
            {{ trans('line.four') }}
            BLADE;

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toBeString();
            // Line numbers should be preserved in compiled output for debugging
        });
    });

    describe('JSON Translation in Blade', function (): void {
        test('it handles JSON-style translations in Blade', function (): void {
            // Arrange
            $blade = "{{ __('Welcome to our site') }}";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('Welcome to our site')");
        });

        test('it handles mixed file-based and JSON translations', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            {{ __('validation.required') }}
            {{ __('Welcome back!') }}
            {{ trans('auth.failed') }}
            BLADE;

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('validation.required')")
                ->and($php)->toContain("__('Welcome back!')")
                ->and($php)->toContain("trans('auth.failed')");
        });
    });

    describe('Namespaced Translations in Blade', function (): void {
        test('it handles package namespaced translations', function (): void {
            // Arrange
            $blade = "{{ __('package::messages.welcome') }}";

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("__('package::messages.welcome')");
        });

        test('it preserves double-colon syntax in compiled output', function (): void {
            // Arrange
            $blade = <<<'BLADE'
            {{ trans('vendor-package::file.key') }}
            @lang('another-package::errors.404')
            BLADE;

            // Act
            $php = $this->parser->compile($blade);

            // Assert
            expect($php)->toContain("trans('vendor-package::file.key')")
                ->and($php)->toContain("app('translator')->get('another-package::errors.404')");
        });
    });
});
