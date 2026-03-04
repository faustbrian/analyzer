<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

use function route;
use function to_route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ResourceRoutes
{
    public function getAllPosts(): RedirectResponse|Redirector
    {
        return to_route('posts.index');
    }

    public function createPost(): RedirectResponse|Redirector
    {
        return to_route('posts.create');
    }

    public function storePost()
    {
        return to_route('posts.store');
    }

    public function showPost($post): string
    {
        return route('posts.show', ['post' => $post]);
    }

    public function editPost($post): string
    {
        return route('posts.edit', $post);
    }

    public function updatePost($post): string
    {
        return route('posts.update', ['post' => $post]);
    }

    public function destroyPost($post): string
    {
        return route('posts.destroy', $post);
    }
}
