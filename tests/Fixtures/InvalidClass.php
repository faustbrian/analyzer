<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Another\MissingClass;
use NonExistent\FakeClass;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidClass
{
    public function broken(): MissingClass
    {
        return new MissingClass();
    }

    public function alsobroken(): FakeClass
    {
        return new FakeClass();
    }
}
