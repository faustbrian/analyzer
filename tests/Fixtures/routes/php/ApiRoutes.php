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
final class ApiRoutes
{
    public function v1Users()
    {
        return route('api.v1.users.index');
    }

    public function v2Post($post)
    {
        return route('api.v2.posts.show', ['post' => $post]);
    }

    public function apiLogin()
    {
        return route('api.v1.auth.login');
    }
}
