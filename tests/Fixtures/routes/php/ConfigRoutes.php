<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use function config;
use function env;
use function route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigRoutes
{
    public function getFromConfig(): string
    {
        return route(config('routes.dashboard'));
    }

    public function getFromEnv(): string
    {
        return route(env('DEFAULT_ROUTE', 'home'));
    }
}
