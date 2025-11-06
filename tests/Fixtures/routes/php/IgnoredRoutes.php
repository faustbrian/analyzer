<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use function route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class IgnoredRoutes
{
    public function adminRoute()
    {
        // This should be ignored based on config
        return route('admin.users.index');
    }

    public function apiRoute()
    {
        // This should be ignored based on config
        return route('api.v1.posts.index');
    }

    public function normalRoute()
    {
        return route('posts.index');
    }
}
