<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests;

use function trans;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class IncludePatternTest
{
    public function test(): void
    {
        // These should be included with includePatterns: ['validation.*', 'auth.*']
        trans('validation.required');
        trans('auth.failed');

        // These should be excluded
        trans('messages.test');
        trans('users.title');
    }
}
