<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use function redirect;
use function route;
use function to_route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ResourceRoutes
{
    public function getAllPosts()
    {
        return redirect()->route('posts.index');
    }

    public function createPost()
    {
        return redirect()->route('posts.create');
    }

    public function storePost()
    {
        return to_route('posts.store');
    }

    public function showPost($post)
    {
        return route('posts.show', ['post' => $post]);
    }

    public function editPost($post)
    {
        return route('posts.edit', $post);
    }

    public function updatePost($post)
    {
        return route('posts.update', ['post' => $post]);
    }

    public function destroyPost($post)
    {
        return route('posts.destroy', $post);
    }
}
