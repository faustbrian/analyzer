<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use InvalidArgumentException;
use SplFileInfo;

use function throw_unless;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ValidClass
{
    public function process(SplFileInfo $file): void
    {
        throw_unless($file->isFile(), InvalidArgumentException::class, 'Not a file');
    }
}
