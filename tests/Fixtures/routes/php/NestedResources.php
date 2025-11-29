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
final class NestedResources
{
    public function getPostComments($post)
    {
        return route('posts.comments.index', ['post' => $post]);
    }

    public function getUserPost($user, $post)
    {
        return route('users.posts.show', [$user, $post]);
    }

    public function getUserPosts($user)
    {
        return route('users.posts.index', $user);
    }
}
