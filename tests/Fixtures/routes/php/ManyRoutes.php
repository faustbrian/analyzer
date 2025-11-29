<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use function redirect;
use function route;
use function to_route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ManyRoutes
{
    // File with many route calls for performance testing
    public function route1()
    {
        return route('posts.index');
    }

    public function route2()
    {
        return route('posts.show', 1);
    }

    public function route3()
    {
        return route('posts.create');
    }

    public function route4()
    {
        return route('posts.edit', 1);
    }

    public function route5()
    {
        return route('users.profile');
    }

    public function route6()
    {
        return route('users.edit');
    }

    public function route7()
    {
        return route('admin.dashboard');
    }

    public function route8()
    {
        return route('admin.users.index');
    }

    public function route9()
    {
        return route('api.v1.users.index');
    }

    public function route10()
    {
        return route('api.v1.posts.index');
    }

    public function route11()
    {
        return route('login');
    }

    public function route12()
    {
        return route('register');
    }

    public function route13()
    {
        return route('dashboard');
    }

    public function route14()
    {
        return route('home');
    }

    public function route15()
    {
        return route('about');
    }

    public function route16()
    {
        return route('contact');
    }

    public function route17()
    {
        return redirect()->route('posts.index');
    }

    public function route18()
    {
        return redirect()->route('users.profile');
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
