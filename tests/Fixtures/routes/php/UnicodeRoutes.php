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
final class UnicodeRoutes
{
    public function russianRoute()
    {
        return route('сайт.главная');
    }

    public function chineseRoute()
    {
        return route('网站.首页');
    }

    public function arabicRoute()
    {
        return route('موقع.الرئيسية');
    }
}
