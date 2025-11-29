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

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ValidRoutes
{
    public function redirectToHome()
    {
        return redirect()->route('home');
    }

    public function redirectToPostsIndex()
    {
        return redirect()->route('posts.index');
    }

    public function getUserProfileUrl()
    {
        return route('users.profile');
    }

    public function getAdminDashboardUrl()
    {
        return route('admin.dashboard');
    }

    public function checkRouteExists()
    {
        if (Route::has('login')) {
            return true;
        }

        return false;
    }
}
