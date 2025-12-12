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
final class ImplicitBinding
{
    public function showPost($post): string
    {
        // $post is a model instance with implicit binding
        return route('posts.show', $post);
    }

    public function editUser($user): string
    {
        return route('users.edit', $user);
    }
}
