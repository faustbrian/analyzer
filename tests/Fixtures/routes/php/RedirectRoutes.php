<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use function redirect;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class RedirectRoutes
{
    public function toLogin()
    {
        return redirect()->route('login');
    }

    public function toDashboard()
    {
        return redirect()->route('dashboard')->with('success', 'Welcome!');
    }

    public function toHome()
    {
        return redirect()->route('home')->withInput();
    }
}
