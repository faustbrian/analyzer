<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analysis\TranslationCallVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

describe('TranslationCallVisitor', function (): void {
    beforeEach(function (): void {
        $this->visitor = new TranslationCallVisitor();
        $this->parser = new ParserFactory()->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->visitor);
    });

    describe('Happy Path - Static Key Detection', function (): void {
        test('it extracts trans() calls with static strings', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans('validation.required');
            trans('auth.failed');
            trans('messages.welcome');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0])->toMatchArray([
                    'key' => 'validation.required',
                    'type' => 'trans',
                    'line' => 2,
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => 'auth.failed',
                    'type' => 'trans',
                    'line' => 3,
                    'dynamic' => false,
                ])
                ->and($calls[2])->toMatchArray([
                    'key' => 'messages.welcome',
                    'type' => 'trans',
                    'line' => 4,
                    'dynamic' => false,
                ]);
        });

        test('it extracts __() calls with static strings', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            __('auth.throttle');
            __('passwords.reset');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'key' => 'auth.throttle',
                    'type' => '__',
                    'line' => 2,
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => 'passwords.reset',
                    'type' => '__',
                    'line' => 3,
                    'dynamic' => false,
                ]);
        });

        test('it extracts Lang::get() calls with static strings', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            use Illuminate\Support\Facades\Lang;

            Lang::get('errors.404');
            Lang::get('success.created');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'key' => 'errors.404',
                    'type' => 'Lang::get',
                    'line' => 4,
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => 'success.created',
                    'type' => 'Lang::get',
                    'line' => 5,
                    'dynamic' => false,
                ]);
        });

        test('it extracts @lang() from compiled Blade templates', function (): void {
            // Skip: Heredoc with PHP code causes Pest parser issues
        })->skip();

        test('it handles nested translation keys with multiple dots', function (): void {
            // Arrange
            $code = "<?php\ntrans('validation.attributes.user.email');\n__('errors.http.404.title');";

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['key'])->toBe('validation.attributes.user.email')
                ->and($calls[1]['key'])->toBe('errors.http.404.title');
        });
    });

    describe('Sad Path - Dynamic Key Detection', function (): void {
        test('it detects dynamic keys using variables', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            $key = 'validation.required';
            trans($key);
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'key' => null,
                    'type' => 'trans',
                    'line' => 3,
                    'dynamic' => true,
                    'reason' => 'Variable used as key',
                ]);
        });

        test('it detects dynamic keys using concatenation', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans('validation.' . $field);
            __('errors.' . $code . '.message');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'key' => null,
                    'type' => 'trans',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'String concatenation',
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => null,
                    'type' => '__',
                    'line' => 3,
                    'dynamic' => true,
                    'reason' => 'String concatenation',
                ]);
        });

        test('it detects dynamic keys using config() calls', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans(config('app.message_key'));
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'key' => null,
                    'type' => 'trans',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'Function call used as key',
                ]);
        });

        test('it detects dynamic keys using method calls', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans($user->getMessageKey());
            __($this->getTranslationKey());
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'key' => null,
                    'type' => 'trans',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'Method call used as key',
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => null,
                    'type' => '__',
                    'line' => 3,
                    'dynamic' => true,
                    'reason' => 'Method call used as key',
                ]);
        });

        test('it detects dynamic keys using ternary operators', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans($isError ? 'errors.generic' : 'success.message');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'key' => null,
                    'type' => 'trans',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'Ternary operator',
                ]);
        });
    });

    describe('Edge Cases', function (): void {
        test('it handles trans() with replacement parameters', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans('validation.required', ['attribute' => 'email']);
            __('welcome.greeting', ['name' => $userName]);
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'key' => 'validation.required',
                    'type' => 'trans',
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => 'welcome.greeting',
                    'type' => '__',
                    'dynamic' => false,
                ]);
        });

        test('it handles trans_choice() calls', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans_choice('messages.apples', 10);
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'key' => 'messages.apples',
                    'type' => 'trans_choice',
                    'dynamic' => false,
                ]);
        });

        test('it handles namespaced package translations', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans('package::file.key');
            __('vendor-package::messages.welcome');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'key' => 'package::file.key',
                    'type' => 'trans',
                    'dynamic' => false,
                    'namespaced' => true,
                    'package' => 'package',
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => 'vendor-package::messages.welcome',
                    'type' => '__',
                    'dynamic' => false,
                    'namespaced' => true,
                    'package' => 'vendor-package',
                ]);
        });

        test('it handles empty translation key strings', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans('');
            __('');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'key' => '',
                    'type' => 'trans',
                    'dynamic' => false,
                    'empty' => true,
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => '',
                    'type' => '__',
                    'dynamic' => false,
                    'empty' => true,
                ]);
        });

        test('it ignores trans() calls without arguments', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans();
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(0);
        });

        test('it handles multiple translation calls in same file', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            class UserController {
                public function show() {
                    $title = __('users.show.title');
                    $message = trans('users.show.message');
                    return view('users.show', compact('title', 'message'));
                }

                public function edit() {
                    $title = __('users.edit.title');
                    return view('users.edit', compact('title'));
                }
            }
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0]['key'])->toBe('users.show.title')
                ->and($calls[1]['key'])->toBe('users.show.message')
                ->and($calls[2]['key'])->toBe('users.edit.title');
        });

        test('it handles unicode characters in translation keys', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans('messages.здравствуй');
            __('greetings.你好');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['key'])->toBe('messages.здравствуй')
                ->and($calls[1]['key'])->toBe('greetings.你好');
        });

        test('it resets state between traversals', function (): void {
            // Arrange
            $code1 = <<<'TESTCODE'
            <?php trans('first.key');
            TESTCODE;
            $code2 = <<<'TESTCODE'
            <?php trans('second.key');
            TESTCODE;

            // Act
            $ast1 = $this->parser->parse($code1);
            $this->traverser->traverse($ast1);
            $calls1 = $this->visitor->getTranslationCalls();

            $ast2 = $this->parser->parse($code2);
            $this->traverser->traverse($ast2);
            $calls2 = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls1)->toHaveCount(1)
                ->and($calls1[0]['key'])->toBe('first.key')
                ->and($calls2)->toHaveCount(1)
                ->and($calls2[0]['key'])->toBe('second.key');
        });
    });

    describe('JSON Translation Support', function (): void {
        test('it detects JSON translation keys without file prefix', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            __('Welcome to our application');
            trans('Good morning');
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'key' => 'Welcome to our application',
                    'type' => '__',
                    'dynamic' => false,
                    'json_style' => true,
                ])
                ->and($calls[1])->toMatchArray([
                    'key' => 'Good morning',
                    'type' => 'trans',
                    'dynamic' => false,
                    'json_style' => true,
                ]);
        });

        test('it distinguishes between file-based and JSON keys', function (): void {
            // Arrange
            $code = <<<'TESTCODE'
            <?php
            trans('validation.required'); // File-based
            trans('Welcome'); // JSON-style (no dots or single word with capital)
            trans('messages.welcome'); // File-based
            TESTCODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getTranslationCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0]['json_style'])->toBeFalse()
                ->and($calls[1]['json_style'])->toBeTrue()
                ->and($calls[2]['json_style'])->toBeFalse();
        });
    });
});
