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
    public function postRoute(): string
    {
        return route('posts.index');
    }

    public function userRoute(): string
    {
        return route('users.profile');
    }

    public function adminRoute(): string
    {
        return route('admin.dashboard');
    }

    public function apiRoute(): string
    {
        return route('api.v1.users.index');
    }
}
