<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

use function route;
use function to_route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ManyRoutes
{
    // File with many route calls for performance testing
    public function route1(): string
    {
        return route('posts.index');
    }

    public function route2(): string
    {
        return route('posts.show', 1);
    }

    public function route3(): string
    {
        return route('posts.create');
    }

    public function route4(): string
    {
        return route('posts.edit', 1);
    }

    public function route5(): string
    {
        return route('users.profile');
    }

    public function route6(): string
    {
        return route('users.edit');
    }

    public function route7(): string
    {
        return route('admin.dashboard');
    }

    public function route8(): string
    {
        return route('admin.users.index');
    }

    public function route9(): string
    {
        return route('api.v1.users.index');
    }

    public function route10(): string
    {
        return route('api.v1.posts.index');
    }

    public function route11(): string
    {
        return route('login');
    }

    public function route12(): string
    {
        return route('register');
    }

    public function route13(): string
    {
        return route('dashboard');
    }

    public function route14(): string
    {
        return route('home');
    }

    public function route15(): string
    {
        return route('about');
    }

    public function route16(): string
    {
        return route('contact');
    }

    public function route17(): Redirector|RedirectResponse
    {
        return to_route('posts.index');
    }

    public function route18(): Redirector|RedirectResponse
    {
        return to_route('users.profile');
    }

    public function route19()
    {
        return to_route('dashboard');
    }

    public function route20()
    {
        return to_route('login');
    }
}
