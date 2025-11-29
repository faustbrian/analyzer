<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\PhpCsFixer\Preset\Standard;
use Cline\PhpCsFixer\ConfigurationFactory;

$config = ConfigurationFactory::createFromPreset(
    new Standard(),
);

/** @var PhpCsFixer\Finder $finder */
$finder = $config->getFinder();
$finder->in([__DIR__.'/src', __DIR__.'/tests'])
    ->notPath('Fixtures/routes/php/SyntaxError.php')
    ->notPath('Fixtures/translations/php/SyntaxError.php');

return $config;
