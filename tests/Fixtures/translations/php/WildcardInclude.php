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
final class WildcardInclude
{
    public function test(): void
    {
        // Should be included with includePatterns: ['validation.attributes.*']
        trans('validation.attributes.email');
        trans('validation.attributes.password');

        // Should be excluded (doesn't match pattern)
        trans('validation.required');
        trans('validation.email');
    }
}
