<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use function to_route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ToRouteHelper
{
    public function toHome()
    {
        return to_route('home');
    }

    public function toPostsIndex()
    {
        return to_route('posts.index');
    }

    public function toUserProfile($userId)
    {
        return to_route('users.profile', ['user' => $userId]);
    }
}
