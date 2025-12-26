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
final class AdminRoutes
{
    public function adminUsers(): string
    {
        return route('admin.users.index');
    }

    public function createPost(): string
    {
        return route('admin.posts.create');
    }

    public function dashboard(): string
    {
        return route('admin.dashboard');
    }
}
