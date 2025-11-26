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
final class MixedNamespaces
{
    public function getAll(): array
    {
        return [
            __('validation.required'), // Should be included
            trans('auth.failed'), // Should be included
            __('messages.welcome'), // Should not be included
            trans('errors.404'), // Should not be included
        ];
    }
}
