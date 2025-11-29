<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Translations;

use function __;
use function config;
use function trans;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class DynamicKeys
{
    public function withVariable(string $key): string
    {
        return trans($key);
    }

    public function withConcatenation(string $field): string
    {
        return __('validation.'.$field);
    }

    public function withConfig(): string
    {
        return trans(config('app.message_key'));
    }
}
