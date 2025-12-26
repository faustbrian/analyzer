<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analysis\RouteCallVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

describe('RouteCallVisitor', function (): void {
    beforeEach(function (): void {
        $this->visitor = new RouteCallVisitor();
        $this->parser = new ParserFactory()->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->visitor);
    });

    describe('Happy Path - Static Route Name Detection', function (): void {
        test('it extracts route() calls with static strings', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('posts.index');
            route('user.profile');
            route('admin.dashboard');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0])->toMatchArray([
                    'name' => 'posts.index',
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'user.profile',
                    'type' => 'route',
                    'line' => 3,
                    'dynamic' => false,
                ])
                ->and($calls[2])->toMatchArray([
                    'name' => 'admin.dashboard',
                    'type' => 'route',
                    'line' => 4,
                    'dynamic' => false,
                ]);
        });

        test('it extracts route() calls with parameters', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('posts.show', ['post' => 1]);
            route('user.profile', ['id' => $userId]);
            route('admin.edit', $params);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0])->toMatchArray([
                    'name' => 'posts.show',
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'user.profile',
                    'type' => 'route',
                    'line' => 3,
                    'dynamic' => false,
                ])
                ->and($calls[2])->toMatchArray([
                    'name' => 'admin.edit',
                    'type' => 'route',
                    'line' => 4,
                    'dynamic' => false,
                ]);
        });

        test('it extracts Route::has() calls with static strings', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            \Illuminate\Support\Facades\Route::has('posts.index');
            \Illuminate\Support\Facades\Route::has('admin.dashboard');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'name' => 'posts.index',
                    'type' => 'Route::has',
                    'line' => 2,
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'admin.dashboard',
                    'type' => 'Route::has',
                    'line' => 3,
                    'dynamic' => false,
                ]);
        });

        test('it extracts redirect()->route() calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            redirect()->route('home');
            return redirect()->route('login');
            redirect()->route('dashboard', ['tab' => 'settings']);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0])->toMatchArray([
                    'name' => 'home',
                    'type' => 'redirect()->route',
                    'line' => 2,
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'login',
                    'type' => 'redirect()->route',
                    'line' => 3,
                    'dynamic' => false,
                ])
                ->and($calls[2])->toMatchArray([
                    'name' => 'dashboard',
                    'type' => 'redirect()->route',
                    'line' => 4,
                    'dynamic' => false,
                ]);
        });

        test('it extracts to_route() helper calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            to_route('home');
            return to_route('posts.index');
            to_route('user.profile', ['id' => 1]);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0])->toMatchArray([
                    'name' => 'home',
                    'type' => 'to_route',
                    'line' => 2,
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'posts.index',
                    'type' => 'to_route',
                    'line' => 3,
                    'dynamic' => false,
                ])
                ->and($calls[2])->toMatchArray([
                    'name' => 'user.profile',
                    'type' => 'to_route',
                    'line' => 4,
                    'dynamic' => false,
                ]);
        });

        test('it handles nested route names with multiple dots', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('admin.users.posts.show');
            route('api.v1.auth.login');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['name'])->toBe('admin.users.posts.show')
                ->and($calls[1]['name'])->toBe('api.v1.auth.login');
        });
    });

    describe('Sad Path - Dynamic Route Name Detection', function (): void {
        test('it detects dynamic route names using variables', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            $routeName = 'posts.index';
            route($routeName);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 3,
                    'dynamic' => true,
                    'reason' => 'Variable used as route name',
                ]);
        });

        test('it detects dynamic route names using concatenation', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('posts.' . $action);
            route($prefix . '.show');
            route('admin.' . $resource . '.index');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'String concatenation',
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 3,
                    'dynamic' => true,
                    'reason' => 'String concatenation',
                ])
                ->and($calls[2])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 4,
                    'dynamic' => true,
                    'reason' => 'String concatenation',
                ]);
        });

        test('it detects dynamic route names using config() calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route(config('routes.dashboard'));
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'Function call used as route name',
                ]);
        });

        test('it detects dynamic route names using method calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route($user->getRouteName());
            route($this->determineRoute());
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'Method call used as route name',
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 3,
                    'dynamic' => true,
                    'reason' => 'Method call used as route name',
                ]);
        });

        test('it detects dynamic route names using ternary operators', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route($isAdmin ? 'admin.dashboard' : 'user.dashboard');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'Ternary operator',
                ]);
        });

        test('it detects dynamic route names using null coalescing', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route($customRoute ?? 'home');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'Null coalescing operator',
                ]);
        });

        test('it detects dynamic route names using static method calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route(SomeClass::getRouteName());
            route(Helper::determineRoute($user));
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'Method call used as route name',
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 3,
                    'dynamic' => true,
                    'reason' => 'Method call used as route name',
                ]);
        });

        test('it detects dynamic route names using array access expressions', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route($routes['dashboard']);
            route($config['routes']['admin']);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 2,
                    'dynamic' => true,
                    'reason' => 'dynamic',
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => null,
                    'type' => 'route',
                    'line' => 3,
                    'dynamic' => true,
                    'reason' => 'dynamic',
                ]);
        });
    });

    describe('Edge Cases', function (): void {
        test('it handles route() calls with absolute parameter', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('posts.show', ['post' => 1], true);
            route('user.profile', $params, false);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'name' => 'posts.show',
                    'type' => 'route',
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'user.profile',
                    'type' => 'route',
                    'dynamic' => false,
                ]);
        });

        test('it handles empty route name strings', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'name' => '',
                    'type' => 'route',
                    'dynamic' => false,
                    'empty' => true,
                ]);
        });

        test('it ignores route() calls without arguments', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route();
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(0);
        });

        test('it handles multiple route calls in same file', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            class UserController {
            public function index() {
            return redirect()->route('users.index');
            }

            public function show($id) {
            if (!Route::has('users.show')) {
            abort(404);
            }
            return view('users.show', ['url' => route('users.show', $id)]);
            }

            public function edit($id) {
            return to_route('users.edit', ['user' => $id]);
            }
            }
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(4)
                ->and($calls[0]['name'])->toBe('users.index')
                ->and($calls[1]['name'])->toBe('users.show')
                ->and($calls[2]['name'])->toBe('users.show')
                ->and($calls[3]['name'])->toBe('users.edit');
        });

        test('it handles unicode characters in route names', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('сайт.главная');
            route('网站.首页');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['name'])->toBe('сайт.главная')
                ->and($calls[1]['name'])->toBe('网站.首页');
        });

        test('it resets state between traversals', function (): void {
            // Arrange
            $code1 = "<?php route('first.route');";
            $code2 = "<?php route('second.route');";

            // Act
            $ast1 = $this->parser->parse($code1);
            $this->traverser->traverse($ast1);
            $calls1 = $this->visitor->getRouteCalls();

            $ast2 = $this->parser->parse($code2);
            $this->traverser->traverse($ast2);
            $calls2 = $this->visitor->getRouteCalls();

            // Assert
            expect($calls1)->toHaveCount(1)
                ->and($calls1[0]['name'])->toBe('first.route')
                ->and($calls2)->toHaveCount(1)
                ->and($calls2[0]['name'])->toBe('second.route');
        });

        test('it handles route names with dashes and underscores', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('user-profile.show');
            route('admin_dashboard.index');
            route('api-v2_users.create');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0]['name'])->toBe('user-profile.show')
                ->and($calls[1]['name'])->toBe('admin_dashboard.index')
                ->and($calls[2]['name'])->toBe('api-v2_users.create');
        });

        test('it handles route names without dots', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('home');
            route('login');
            route('dashboard');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0]['name'])->toBe('home')
                ->and($calls[1]['name'])->toBe('login')
                ->and($calls[2]['name'])->toBe('dashboard');
        });
    });

    describe('Response::route() and Redirector Methods', function (): void {
        test('it extracts response()->route() calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            response()->route('home');
            return response()->route('posts.index');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'name' => 'home',
                    'type' => 'response()->route',
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'posts.index',
                    'type' => 'response()->route',
                    'dynamic' => false,
                ]);
        });

        test('it extracts Redirector::route() calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            $redirector = new \Illuminate\Routing\Redirector(app('url'));
            $redirector->route('users.index');
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(1)
                ->and($calls[0])->toMatchArray([
                    'name' => 'users.index',
                    'type' => '$redirector->route',
                    'dynamic' => false,
                ]);
        });
    });

    describe('Blade Compiled Output', function (): void {
        test('it extracts route() from compiled Blade templates', function (): void {
            // Arrange
            $code = <<<'PHP'
<?php
echo e(route('home'));
echo e(route('posts.index', ['id' => 1]));
PHP;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['name'])->toBe('home')
                ->and($calls[1]['name'])->toBe('posts.index');
        });

        test('it extracts Route::has() from compiled Blade', function (): void {
            // Arrange
            // Blade @if(Route::has('users.show')) compiles to PHP
            $code = <<<'CODE'
            <?php if(Route::has('users.show')): ?>
            <a href="<?php echo e(route('users.show', $user)); ?>">View</a>
            <?php endif; ?>
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'name' => 'users.show',
                    'type' => 'Route::has',
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'users.show',
                    'type' => 'route',
                ]);
        });
    });

    describe('URL Generator Methods', function (): void {
        test('it extracts URL::route() calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            \Illuminate\Support\Facades\URL::route('api.users');
            \Illuminate\Support\Facades\URL::route('posts.show', ['post' => 1]);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0])->toMatchArray([
                    'name' => 'api.users',
                    'type' => 'URL::route',
                    'dynamic' => false,
                ])
                ->and($calls[1])->toMatchArray([
                    'name' => 'posts.show',
                    'type' => 'URL::route',
                    'dynamic' => false,
                ]);
        });

        test('it extracts url()->route() calls', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            url()->route('home');
            url()->route('dashboard', ['tab' => 'settings']);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['name'])->toBe('home')
                ->and($calls[1]['name'])->toBe('dashboard');
        });
    });

    describe('Route Naming Patterns', function (): void {
        test('it handles resource route patterns', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('posts.index');
            route('posts.create');
            route('posts.store');
            route('posts.show', $post);
            route('posts.edit', $post);
            route('posts.update', $post);
            route('posts.destroy', $post);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(7)
                ->and($calls[0]['name'])->toBe('posts.index')
                ->and($calls[1]['name'])->toBe('posts.create')
                ->and($calls[2]['name'])->toBe('posts.store')
                ->and($calls[3]['name'])->toBe('posts.show')
                ->and($calls[4]['name'])->toBe('posts.edit')
                ->and($calls[5]['name'])->toBe('posts.update')
                ->and($calls[6]['name'])->toBe('posts.destroy');
        });

        test('it handles nested resource routes', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('posts.comments.index', $post);
            route('users.posts.show', [$user, $post]);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['name'])->toBe('posts.comments.index')
                ->and($calls[1]['name'])->toBe('users.posts.show');
        });

        test('it handles API route patterns', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('api.v1.users.index');
            route('api.v2.posts.show', $post);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['name'])->toBe('api.v1.users.index')
                ->and($calls[1]['name'])->toBe('api.v2.posts.show');
        });
    });

    describe('Route Model Binding', function (): void {
        test('it extracts route names with model parameters', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('posts.show', $post);
            route('users.edit', $user);
            route('posts.comments.show', [$post, $comment]);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0]['name'])->toBe('posts.show')
                ->and($calls[1]['name'])->toBe('users.edit')
                ->and($calls[2]['name'])->toBe('posts.comments.show');
        });

        test('it handles explicit parameter arrays', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            route('posts.show', ['post' => $postId]);
            route('users.edit', ['user' => $user, 'tab' => 'settings']);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['name'])->toBe('posts.show')
                ->and($calls[1]['name'])->toBe('users.edit');
        });
    });

    describe('Chained Method Calls', function (): void {
        test('it extracts routes from complex redirect chains', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            return redirect()->route('home')->with('success', 'Saved!');
            redirect()->route('login')->withInput();
            redirect()->route('dashboard')->withErrors($errors);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(3)
                ->and($calls[0]['name'])->toBe('home')
                ->and($calls[1]['name'])->toBe('login')
                ->and($calls[2]['name'])->toBe('dashboard');
        });

        test('it extracts routes from response chains', function (): void {
            // Arrange
            $code = <<<'CODE'
            <?php
            return response()->route('api.users')->header('X-Custom', 'value');
            response()->route('api.posts')->json(['status' => 'ok']);
CODE;

            $ast = $this->parser->parse($code);

            // Act
            $this->traverser->traverse($ast);
            $calls = $this->visitor->getRouteCalls();

            // Assert
            expect($calls)->toHaveCount(2)
                ->and($calls[0]['name'])->toBe('api.users')
                ->and($calls[1]['name'])->toBe('api.posts');
        });
    });
});
