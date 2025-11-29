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
final class RoutesWithParams
{
    public function showPost($postId)
    {
        return route('posts.show', ['post' => $postId]);
    }

    public function editUser($userId, $tab = 'profile')
    {
        return route('users.edit', [
            'user' => $userId,
            'tab' => $tab,
        ]);
    }

    public function showWithAbsolute($postId)
    {
        return route('posts.show', ['post' => $postId], true);
    }
}
