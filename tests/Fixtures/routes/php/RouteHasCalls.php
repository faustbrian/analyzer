<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use Illuminate\Support\Facades\Route;

use function route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class RouteHasCalls
{
    public function checkPostShow()
    {
        if (Route::has('posts.show')) {
            return route('posts.show', 1);
        }

        return null;
    }

    public function checkUserEdit()
    {
        return Route::has('users.edit') ? route('users.edit', 1) : null;
    }
}
