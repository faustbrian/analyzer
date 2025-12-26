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

use function redirect;
use function to_route;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class MixedNamedUnnamed
{
    public function handleRoutes(): Redirector|RedirectResponse
    {
        return to_route('home');
    }

    public function handleUnnamedRoute(): Redirector|RedirectResponse
    {
        return redirect('/unnamed-path');
    }
}
