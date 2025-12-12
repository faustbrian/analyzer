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
final class QuestionMarkPattern
{
    public function test(): void
    {
        // Should be ignored with pattern 'test.key?'
        trans('test.key1');
        trans('test.key2');
        trans('test.keyA');
    }
}
