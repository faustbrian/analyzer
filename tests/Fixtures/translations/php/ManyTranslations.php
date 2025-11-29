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
final class ManyTranslations
{
    public function getAllMessages(): array
    {
        return [
            __('validation.required'),
            trans('validation.email'),
            __('validation.min.numeric'),
            trans('validation.max.string'),
            __('auth.failed'),
            trans('auth.password'),
            __('auth.throttle'),
            trans('messages.welcome'),
            __('messages.goodbye'),
            trans('messages.user.created'),
            __('messages.user.updated'),
            trans('messages.user.deleted'),
            __('errors.404'),
            trans('errors.500'),
            __('errors.generic'),
            trans('passwords.reset'),
            __('passwords.sent'),
            trans('passwords.token'),
            __('Welcome'),
            trans('Good morning'),
        ];
    }
}
