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
final class InvalidTranslations
{
    public function show(): string
    {
        $nonexistent = trans('validation.nonexistent');
        $missing = __('messages.missing');
        $valid = trans('auth.failed');

        return $nonexistent.$missing.$valid;
    }
}
