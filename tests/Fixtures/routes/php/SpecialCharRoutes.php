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
final class SpecialCharRoutes
{
    public function dashRoute(): string
    {
        return route('user-profile.show');
    }

    public function underscoreRoute(): string
    {
        return route('admin_dashboard.index');
    }

    public function mixedRoute(): string
    {
        return route('api-v2_users.create');
    }
}
