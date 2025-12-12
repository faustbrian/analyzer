<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Translations;

use function __;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class SpanishOnly
{
    public function getMessage(): string
    {
        // This key only exists in Spanish locale
        return __('messages.only_in_es');
    }
}
