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
final class ConcatenatedKeys
{
    public function messages(string $type): string
    {
        return trans('messages.'.$type);
    }

    public function errors(string $code, string $level): string
    {
        return __('errors.'.$code.'.'.$level);
    }
}
