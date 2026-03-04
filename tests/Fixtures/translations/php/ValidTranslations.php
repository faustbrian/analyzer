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
final class ValidTranslations
{
    public function index(): string
    {
        $required = trans('validation.required');
        $failed = __('auth.failed');
        $welcome = trans('messages.welcome');

        return $required.$failed.$welcome;
    }
}
