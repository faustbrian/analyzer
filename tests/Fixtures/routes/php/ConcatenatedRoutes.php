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
final class ConcatenatedRoutes
{
    public function concatenateWithVariable($action)
    {
        return route('posts.'.$action);
    }

    public function concatenatePrefix($prefix)
    {
        return route($prefix.'.show');
    }

    public function multipleConcatenation($prefix, $resource, $action)
    {
        return route($prefix.'.'.$resource.'.'.$action);
    }
}
