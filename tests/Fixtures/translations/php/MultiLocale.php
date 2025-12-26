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
final class MultiLocale
{
    public function messages(): array
    {
        return [
            'en' => __('messages.welcome'), // Exists in en, es, fr
            'es' => trans('messages.only_in_es'), // Only in es
            'fr' => __('messages.only_in_fr'), // Only in fr
        ];
    }
}
