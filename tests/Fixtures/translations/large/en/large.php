<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$translations = [];

for ($i = 1; $i <= 1_000; ++$i) {
    $translations["key_{$i}"] = "Translation {$i}";
}

return $translations;
