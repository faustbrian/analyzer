<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Translations;

use function __;
use function trans;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class UnicodeKeys
{
    public function chinese(): string
    {
        return __('messages.你好');
    }

    public function russian(): string
    {
        return trans('greetings.здравствуй');
    }
}
