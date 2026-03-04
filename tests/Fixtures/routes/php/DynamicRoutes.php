<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Routes;

use function config;
use function route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class DynamicRoutes
{
    public function dynamicVariable(): string
    {
        $routeName = 'posts.index';

        return route($routeName);
    }

    public function dynamicConcatenation(string $action): string
    {
        return route('posts.'.$action);
    }

    public function dynamicConfig(): string
    {
        return route(config('routes.dashboard'));
    }
}
