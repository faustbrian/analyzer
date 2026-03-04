<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Translations;

use function mb_strtoupper;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class NoTranslations
{
    public function calculate(int $a, int $b): int
    {
        return $a + $b;
    }

    public function format(string $text): string
    {
        return mb_strtoupper($text);
    }
}
