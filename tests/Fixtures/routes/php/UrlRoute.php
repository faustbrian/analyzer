<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use Illuminate\Support\Facades\URL;

use function url;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class UrlRoute
{
    public function apiUsers()
    {
        return URL::route('api.users');
    }

    public function apiPosts()
    {
        return URL::route('api.posts', ['limit' => 10]);
    }

    public function urlHelper()
    {
        return url()->route('home');
    }
}
