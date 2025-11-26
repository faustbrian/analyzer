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
final class MixedPatterns
{
    public function postRoute()
    {
        return route('posts.index');
    }

    public function userRoute()
    {
        return route('users.profile');
    }

    public function adminRoute()
    {
        return route('admin.dashboard');
    }

    public function apiRoute()
    {
        return route('api.v1.users.index');
    }
}
