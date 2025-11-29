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

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class EmptyNames
{
    public function emptyRouteName()
    {
        return route('');
    }

    public function emptyRedirect()
    {
        return redirect()->route('');
    }
}
